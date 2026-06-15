<?php

namespace App\Services\Yandex;

use App\Enums\ParseStatus;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Tier 1 — fast path. Fetches the server-rendered reviews card over plain HTTP
 * (no browser) and extracts the embedded state: average rating, ratings count,
 * reviews count, and the first ~50 reviews.
 *
 * Works from a datacenter IP against yandex.com (.ru blocks datacenter IPs).
 */
class YandexReviewsClient
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    public function __construct(private readonly HttpFactory $http)
    {
    }

    /** Tier 1 — counters + first page. */
    public function fetchSummary(YandexUrl $org): ParseResult
    {
        return $this->fetchPage($org, 1);
    }

    /**
     * Tier 2 — all reviews via server-side pagination. The reviews card
     * paginates with `?page=N` (50 each) over plain HTTP, so the full set
     * (~600, Yandex's cap) is retrieved without a headless browser, without
     * the signed `fetchReviews` API, and without datacenter rate-limiting on
     * that endpoint. Pages are paced; the loop stops at the first empty page
     * or the configured cap.
     */
    public function fetchAllPages(YandexUrl $org): ParseResult
    {
        $cap = (int) config('yandex.review_cap', 600);
        $maxPages = (int) ceil($cap / 50) + 1;

        $first = $this->fetchPage($org, 1);
        if (! $first->isOk()) {
            return $first;
        }

        /** @var array<string, ReviewDto> $byId */
        $byId = [];
        foreach ($first->reviews as $dto) {
            $byId[$dto->reviewId] = $dto;
        }

        for ($page = 2; $page <= $maxPages && count($byId) < $cap; $page++) {
            usleep(400_000); // pace requests
            $result = $this->fetchPage($org, $page);
            if (! $result->isOk() || $result->reviews === []) {
                break; // reached the cap / no more pages
            }
            foreach ($result->reviews as $dto) {
                $byId[$dto->reviewId] = $dto;
            }
        }

        return new ParseResult(
            status: ParseStatus::Ok,
            name: $first->name,
            averageRating: $first->averageRating,
            ratingsCount: $first->ratingsCount,
            reviewsCount: $first->reviewsCount,
            reviews: array_values($byId),
        );
    }

    public function fetchPage(YandexUrl $org, int $page = 1): ParseResult
    {
        $base = config('yandex.base_url', 'https://yandex.com');

        try {
            $response = $this->http
                ->withHeaders([
                    'User-Agent' => self::UA,
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->timeout(25)
                ->get($org->reviewsUrl($base, $page));
        } catch (\Throwable $e) {
            Log::warning('Yandex fetch failed', ['id' => $org->businessId, 'page' => $page, 'error' => $e->getMessage()]);

            return ParseResult::failure(ParseStatus::Unreachable);
        }

        if (! $response->successful()) {
            return ParseResult::failure(ParseStatus::Unreachable);
        }

        $html = $response->body();
        if ($html === '' || strlen($html) < 1000) {
            return ParseResult::failure(ParseStatus::Empty);
        }

        if ($this->looksLikeCaptcha($html)) {
            return ParseResult::failure(ParseStatus::Captcha);
        }

        return $this->extract($html);
    }

    private function looksLikeCaptcha(string $html): bool
    {
        // Only treat as captcha when the challenge is actually served (no review payload).
        $hasReviews = str_contains($html, '"reviewId"');
        $hasChallenge = (bool) preg_match('/showcaptcha|checkcaptcha|smartcaptcha|captcha\.yandex/i', $html);

        return $hasChallenge && ! $hasReviews;
    }

    private function extract(string $html): ParseResult
    {
        $node = $this->findRatingNode($html);

        if ($node === null) {
            // Counters can still be recovered from og:description as a fallback.
            $fallback = $this->countersFromMeta($html);
            if ($fallback === null) {
                return ParseResult::failure(ParseStatus::MarkupChanged);
            }

            return new ParseResult(
                status: ParseStatus::Ok,
                name: $fallback['name'],
                averageRating: $fallback['average'],
                ratingsCount: $fallback['ratings'],
                reviewsCount: $fallback['reviews'],
            );
        }

        $rating = $node['ratingData'] ?? [];
        $reviews = [];
        foreach ($node['reviewResults']['reviews'] ?? [] as $raw) {
            if (is_array($raw) && ($dto = ReviewDto::fromYandex($raw))) {
                $reviews[] = $dto;
            }
        }

        return new ParseResult(
            status: ParseStatus::Ok,
            name: $node['name'] ?? $node['title'] ?? null,
            averageRating: isset($rating['ratingValue']) ? (float) $rating['ratingValue'] : null,
            ratingsCount: isset($rating['ratingCount']) ? (int) $rating['ratingCount'] : null,
            reviewsCount: isset($rating['reviewCount']) ? (int) $rating['reviewCount'] : null,
            reviews: $reviews,
        );
    }

    /** Decode the largest application/json state blob and locate the org node carrying ratingData. */
    private function findRatingNode(string $html): ?array
    {
        if (! preg_match_all('#<script[^>]*type="application/json"[^>]*>(.*?)</script>#s', $html, $m)) {
            return null;
        }

        $blocks = $m[1];
        usort($blocks, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($blocks as $block) {
            $data = json_decode(html_entity_decode($block, ENT_QUOTES), true);
            if (! is_array($data)) {
                $data = json_decode($block, true);
            }
            if (! is_array($data)) {
                continue;
            }

            $found = $this->searchRatingNode($data);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function searchRatingNode(array $node): ?array
    {
        if (isset($node['ratingData']) && is_array($node['ratingData'])
            && (isset($node['ratingData']['ratingCount']) || isset($node['ratingData']['reviewCount']))) {
            return $node;
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $found = $this->searchRatingNode($value);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /** Fallback: "Рейтинг 5,0 на основе 174301 оценки и 41057 отзывов" from og:description. */
    private function countersFromMeta(string $html): ?array
    {
        if (! preg_match('#og:description"\s+content="([^"]+)"#', $html, $m)) {
            return null;
        }

        $desc = html_entity_decode($m[1], ENT_QUOTES);
        if (! preg_match('#Рейтинг\s+([\d,\.]+)\s+на основе\s+([\d\s]+)\s+оцен\w+\s+и\s+([\d\s]+)\s+отзыв#u', $desc, $mm)) {
            return null;
        }

        $name = null;
        if (preg_match('#og:title"\s+content="([^"]+)"#', $html, $t)) {
            $name = trim(explode(',', html_entity_decode($t[1], ENT_QUOTES))[0]);
        }

        return [
            'name' => $name,
            'average' => (float) str_replace(',', '.', $mm[1]),
            'ratings' => (int) preg_replace('/\s+/', '', $mm[2]),
            'reviews' => (int) preg_replace('/\s+/', '', $mm[3]),
        ];
    }
}
