<?php

namespace App\Jobs;

use App\Enums\ParseStatus;
use App\Models\Organization;
use App\Services\Yandex\ParseResult;
use App\Services\Yandex\YandexReviewsScraper;
use App\Services\Yandex\YandexUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ParseOrganizationReviews implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240;
    public int $tries = 2;

    public function __construct(public int $organizationId)
    {
    }

    public function handle(YandexReviewsScraper $scraper): void
    {
        $org = Organization::find($this->organizationId);
        if (! $org) {
            return;
        }

        $url = new YandexUrl($org->business_id, $this->slugFrom($org->source_url));
        $result = $scraper->fetchAll($url);

        if (! $result->isOk()) {
            $org->update(['parse_status' => $result->status]);

            return;
        }

        $this->persist($org, $result);
    }

    private function persist(Organization $org, ParseResult $result): void
    {
        foreach (array_chunk($result->reviews, 200) as $chunk) {
            $rows = array_map(fn ($r) => [
                'organization_id' => $org->id,
                ...$r->toModelArray(),
                'updated_at' => now(),
                'created_at' => now(),
            ], $chunk);

            $org->reviews()->upsert(
                $rows,
                ['organization_id', 'review_id'],
                ['author', 'rating', 'text', 'reviewed_at', 'updated_at'],
            );
        }

        $org->update([
            'name' => $result->name ?? $org->name,
            'average_rating' => $result->averageRating ?? $org->average_rating,
            'ratings_count' => $result->ratingsCount ?? $org->ratings_count,
            'reviews_count' => $result->reviewsCount ?? $org->reviews_count,
            'parse_status' => ParseStatus::Ok,
            'parsed_at' => now(),
        ]);
    }

    private function slugFrom(string $url): ?string
    {
        return preg_match('#/maps/org/([^/]+)/\d+#', $url, $m) && ! ctype_digit($m[1]) ? $m[1] : null;
    }
}
