<?php

namespace App\Http\Controllers;

use App\Enums\ParseStatus;
use App\Http\Requests\StoreSourceRequest;
use App\Http\Resources\OrganizationResource;
use App\Jobs\ParseOrganizationReviews;
use App\Models\Organization;
use App\Services\Yandex\YandexReviewsClient;
use App\Services\Yandex\YandexUrl;

class SettingsController extends Controller
{
    public function __construct(private readonly YandexReviewsClient $client)
    {
    }

    public function store(StoreSourceRequest $request)
    {
        $parsed = YandexUrl::tryParse($request->string('url'));
        $existing = Organization::where('business_id', $parsed->businessId)->first();

        // Cache hit: this org was fully parsed recently — serve from the DB
        // instead of re-parsing. The manual "refresh" bypasses this.
        if ($existing && $this->isFresh($existing)) {
            $existing->update(['source_url' => $request->string('url')]);
            $existing->reviews_count_loaded = $existing->reviews()->count();

            return (new OrganizationResource($existing))->additional(['cached' => true]);
        }

        $summary = $this->client->fetchSummary($parsed);

        $org = Organization::updateOrCreate(
            ['business_id' => $parsed->businessId],
            [
                'source_url' => $request->string('url'),
                'name' => $summary->name,
                'average_rating' => $summary->averageRating,
                'ratings_count' => $summary->ratingsCount,
                'reviews_count' => $summary->reviewsCount,
                // Mark a full parse as in progress; the job sets the final status.
                'parse_status' => ParseStatus::Pending,
                'parsed_at' => null,
            ],
        );

        // Seed the cache with the first page immediately (fast path).
        if ($summary->isOk() && $summary->reviews !== []) {
            $this->seedReviews($org, $summary->reviews);
        }

        // Always run the full parse in the background — also self-heals a
        // transient failure of the synchronous first-page fetch.
        ParseOrganizationReviews::dispatch($org->id);

        return new OrganizationResource($org->fresh());
    }

    private function isFresh(Organization $org): bool
    {
        $ttl = (int) config('yandex.cache_ttl_hours', 24);

        return $org->parse_status === ParseStatus::Ok
            && $org->parsed_at !== null
            && $org->parsed_at->gt(now()->subHours($ttl))
            && $org->reviews()->exists();
    }

    private function seedReviews(Organization $org, array $reviews): void
    {
        $rows = array_map(fn ($r) => [
            'organization_id' => $org->id,
            ...$r->toModelArray(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $reviews);

        $org->reviews()->upsert(
            $rows,
            ['organization_id', 'review_id'],
            ['author', 'rating', 'text', 'reviewed_at', 'updated_at'],
        );
    }
}
