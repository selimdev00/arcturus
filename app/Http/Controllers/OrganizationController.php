<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrganizationResource;
use App\Http\Resources\ReviewResource;
use App\Jobs\ParseOrganizationReviews;
use App\Models\Organization;

class OrganizationController extends Controller
{
    /** The app tracks a single configured organization. */
    private function current(): ?Organization
    {
        return Organization::latest('id')->first();
    }

    public function show()
    {
        $org = $this->current();
        if (! $org) {
            return response()->json(null, 404);
        }

        $org->reviews_count_loaded = $org->reviews()->count();

        return new OrganizationResource($org);
    }

    public function reviews()
    {
        $org = $this->current();
        if (! $org) {
            return response()->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]]);
        }

        $page = $org->reviews()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->paginate(50);

        return ReviewResource::collection($page);
    }

    public function reparse()
    {
        $org = $this->current();
        if (! $org) {
            return response()->json(null, 404);
        }

        $org->update(['parse_status' => \App\Enums\ParseStatus::Pending, 'parsed_at' => null]);
        ParseOrganizationReviews::dispatch($org->id);

        return response()->noContent();
    }
}
