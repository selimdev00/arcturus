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
        $summary = $this->client->fetchSummary($parsed);

        $org = Organization::updateOrCreate(
            ['business_id' => $parsed->businessId],
            [
                'source_url' => $request->string('url'),
                'name' => $summary->name,
                'average_rating' => $summary->averageRating,
                'ratings_count' => $summary->ratingsCount,
                'reviews_count' => $summary->reviewsCount,
                'parse_status' => $summary->status,
            ],
        );

        // Seed the cache with the first page immediately (fast path).
        if ($summary->isOk() && $summary->reviews !== []) {
            $this->seedReviews($org, $summary->reviews);
        }

        // Kick the full headless parse in the background.
        if (in_array($summary->status, [ParseStatus::Ok, ParseStatus::Pending], true)) {
            ParseOrganizationReviews::dispatch($org->id);
        }

        return new OrganizationResource($org->fresh());
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
