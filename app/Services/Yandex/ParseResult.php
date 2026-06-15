<?php

namespace App\Services\Yandex;

use App\Enums\ParseStatus;

/** Outcome of a parse (summary tier or full tier). */
class ParseResult
{
    /** @param ReviewDto[] $reviews */
    public function __construct(
        public readonly ParseStatus $status,
        public readonly ?string $name = null,
        public readonly ?float $averageRating = null,
        public readonly ?int $ratingsCount = null,
        public readonly ?int $reviewsCount = null,
        public readonly array $reviews = [],
    ) {
    }

    public static function failure(ParseStatus $status): self
    {
        return new self(status: $status);
    }

    public function isOk(): bool
    {
        return $this->status === ParseStatus::Ok;
    }
}
