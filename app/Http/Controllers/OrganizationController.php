<?php

namespace App\Http\Controllers;

use App\Enums\ParseStatus;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\ReviewResource;
use App\Jobs\ParseOrganizationReviews;
use App\Models\Organization;

class OrganizationController extends Controller
{
    /** All parsed organizations, newest first, with stored-review counts. */
    public function index()
    {
        $orgs = Organization::query()
            ->withCount('reviews as reviews_stored_count')
            ->latest('id')
            ->get()
            ->each(fn (Organization $o) => $o->reviews_count_loaded = $o->reviews_stored_count);

        return OrganizationResource::collection($orgs);
    }

    public function show(Organization $organization)
    {
        $organization->reviews_count_loaded = $organization->reviews()->count();

        return new OrganizationResource($organization);
    }

    public function reviews(Organization $organization)
    {
        $page = $organization->reviews()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->paginate(50);

        return ReviewResource::collection($page);
    }

    public function reparse(Organization $organization)
    {
        $organization->update(['parse_status' => ParseStatus::Pending, 'parsed_at' => null]);
        ParseOrganizationReviews::dispatch($organization->id);

        return response()->noContent();
    }
}
