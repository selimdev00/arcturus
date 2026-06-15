<?php

namespace App\Services\Yandex;

use App\Enums\ParseStatus;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

/**
 * Tier 2 — full set. Drives a headless browser to the reviews card and lets
 * Yandex's own JS sign and fire the paginated `fetchReviews` requests; we
 * intercept those JSON responses while scrolling to the ~600 cap. The first
 * page is read from the server-rendered state. No DOM-class scraping, no
 * reverse-engineering of the request signature.
 */
class YandexReviewsScraper
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    public function fetchAll(YandexUrl $org): ParseResult
    {
        $base = config('yandex.base_url', 'https://yandex.com');
        $maxScrolls = (int) config('yandex.max_scrolls', 40);

        try {
            $raw = $this->browser($org->reviewsUrl($base))
                ->evaluate($this->collectorScript($maxScrolls));
        } catch (\Throwable $e) {
            Log::warning('Yandex tier2 scrape failed', ['id' => $org->businessId, 'error' => $e->getMessage()]);

            return ParseResult::failure(ParseStatus::Unreachable);
        }

        $payload = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($payload)) {
            return ParseResult::failure(ParseStatus::MarkupChanged);
        }

        if (($payload['captcha'] ?? false) === true) {
            return ParseResult::failure(ParseStatus::Captcha);
        }

        $reviews = [];
        foreach ($payload['reviews'] ?? [] as $raw) {
            if (is_array($raw) && ($dto = ReviewDto::fromYandex($raw))) {
                $reviews[] = $dto;
            }
        }

        if ($reviews === [] && empty($payload['ratingData'])) {
            return ParseResult::failure(ParseStatus::Empty);
        }

        $rating = $payload['ratingData'] ?? [];

        return new ParseResult(
            status: ParseStatus::Ok,
            name: $payload['name'] ?? null,
            averageRating: isset($rating['ratingValue']) ? (float) $rating['ratingValue'] : null,
            ratingsCount: isset($rating['ratingCount']) ? (int) $rating['ratingCount'] : null,
            reviewsCount: isset($rating['reviewCount']) ? (int) $rating['reviewCount'] : null,
            reviews: $reviews,
        );
    }

    private function browser(string $url): Browsershot
    {
        $b = Browsershot::url($url)
            ->userAgent(self::UA)
            ->setExtraHttpHeaders(['Accept-Language' => 'ru-RU,ru;q=0.9'])
            ->windowSize(1280, 2200)
            ->noSandbox()
            ->timeout(180)
            ->setOption('args', array_values(array_filter([
                '--no-sandbox',
                '--disable-dev-shm-usage',
                config('yandex.proxy') ? '--proxy-server='.config('yandex.proxy') : null,
            ])));

        if ($path = config('yandex.chrome_path')) {
            $b->setChromePath($path);
        }

        return $b;
    }

    /** In-page collector: read SSR first page, hook fetch, scroll to cap, return JSON. */
    private function collectorScript(int $maxScrolls): string
    {
        return <<<JS
        (async () => {
          const sleep = (ms) => new Promise(r => setTimeout(r, ms));
          const out = { name: null, ratingData: null, reviews: [], captcha: false };
          const byId = new Map();

          // wait for the reviews list to render (up to ~12s)
          for (let i = 0; i < 24 && !document.querySelector('.business-review-view'); i++) {
            await sleep(500);
          }

          if (/showcaptcha|checkcaptcha|smartcaptcha/i.test(document.documentElement.innerHTML)
              && !document.querySelector('.business-review-view')) {
            out.captcha = true; return JSON.stringify(out);
          }

          // 1) first page + counters from the server-rendered state blob
          try {
            const blocks = [...document.querySelectorAll('script[type="application/json"]')]
              .map(s => s.textContent).sort((a, b) => b.length - a.length);
            for (const b of blocks) {
              let data; try { data = JSON.parse(b); } catch (e) { continue; }
              const stack = data?.stack?.[0]?.results?.items || [];
              const item = stack.find(i => i && i.ratingData);
              if (item) {
                out.name = item.name || item.title || null;
                out.ratingData = item.ratingData || null;
                for (const r of (item.reviewResults?.reviews || [])) {
                  if (r?.reviewId) byId.set(r.reviewId, r);
                }
                break;
              }
            }
          } catch (e) {}

          // 2) hook fetch to capture signed fetchReviews JSON pages
          const orig = window.fetch;
          window.fetch = async function (...args) {
            const res = await orig.apply(this, args);
            try {
              const url = (typeof args[0] === 'string') ? args[0] : args[0]?.url;
              if (url && /fetchReviews/.test(url)) {
                res.clone().json().then(j => {
                  for (const r of (j?.data?.reviews || [])) {
                    if (r?.reviewId) byId.set(r.reviewId, r);
                  }
                }).catch(() => {});
              }
            } catch (e) {}
            return res;
          };

          // 3) scroll the reviews container to the cap. There are several
          // .scroll__container nodes on the page — pick the one that actually
          // wraps the review cards (nearest scrollable ancestor of a card).
          const card = document.querySelector('.business-review-view');
          let sc = card ? card.closest('.scroll__container') : null;
          if (!sc) {
            sc = [...document.querySelectorAll('.scroll__container')]
              .find(el => el.querySelector('.business-review-view'))
              || document.querySelector('.scroll__container');
          }
          if (sc) {
            let stable = 0, prev = byId.size;
            for (let i = 0; i < {$maxScrolls} && stable < 3; i++) {
              sc.scrollTop = sc.scrollHeight;
              await sleep(1000);
              const now = byId.size;
              stable = (now === prev) ? stable + 1 : 0;
              prev = now;
            }
          }
          window.fetch = orig;

          out.reviews = [...byId.values()];
          return JSON.stringify(out);
        })()
        JS;
    }
}
