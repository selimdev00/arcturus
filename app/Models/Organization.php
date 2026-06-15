<?php

namespace App\Models;

use App\Enums\ParseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'business_id', 'source_url', 'name', 'average_rating',
        'ratings_count', 'reviews_count', 'parse_status', 'parsed_at',
    ];

    protected $casts = [
        'ratings_count' => 'integer',
        'reviews_count' => 'integer',
        'parse_status' => ParseStatus::class,
        'parsed_at' => 'datetime',
    ];

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
