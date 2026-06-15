<?php

namespace App\Http\Resources;

use App\Enums\ParseStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ParseStatus $status */
        $status = $this->parse_status;

        return [
            'id' => $this->id,
            'url' => $this->source_url,
            'businessId' => $this->business_id,
            'name' => $this->name,
            'averageRating' => $this->average_rating !== null ? round((float) $this->average_rating, 1) : null,
            'ratingsCount' => $this->ratings_count,
            'reviewsCount' => $this->reviews_count,
            'reviewsStored' => $this->reviews_count_loaded ?? $this->reviews()->count(),
            'parseStatus' => $status->value,
            'parseStatusLabel' => $status->label(),
            'parsedAt' => $this->parsed_at?->toIso8601String(),
        ];
    }
}
