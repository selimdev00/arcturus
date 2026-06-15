<?php

namespace App\Services\Yandex;

use Carbon\CarbonImmutable;

/** A single normalized review, source-agnostic (HTML state or fetchReviews JSON). */
class ReviewDto
{
    public function __construct(
        public readonly string $reviewId,
        public readonly ?string $author,
        public readonly ?int $rating,
        public readonly ?string $text,
        public readonly ?CarbonImmutable $reviewedAt,
    ) {
    }

    /** Build from a Yandex review object (same shape in SSR state and fetchReviews JSON). */
    public static function fromYandex(array $r): ?self
    {
        $id = $r['reviewId'] ?? null;
        if (! $id) {
            return null;
        }

        $time = $r['updatedTime'] ?? $r['time'] ?? null;

        return new self(
            reviewId: (string) $id,
            author: is_array($r['author'] ?? null) ? ($r['author']['name'] ?? null) : ($r['author'] ?? null),
            rating: isset($r['rating']) ? (int) $r['rating'] : null,
            text: $r['text'] ?? null,
            reviewedAt: $time ? CarbonImmutable::parse($time) : null,
        );
    }

    public function toModelArray(): array
    {
        return [
            'review_id' => $this->reviewId,
            'author' => $this->author,
            'rating' => $this->rating,
            'text' => $this->text,
            'reviewed_at' => $this->reviewedAt,
        ];
    }
}
