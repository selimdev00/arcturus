<?php

namespace Tests\Feature;

use App\Jobs\ParseOrganizationReviews;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SettingsFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'a@b.test', 'password' => Hash::make('secret')]);
    }

    public function test_guest_cannot_store_source(): void
    {
        $this->postJson('/api/settings/source', ['url' => 'https://yandex.ru/maps/org/gum/1331623198/'])
            ->assertUnauthorized();
    }

    public function test_stores_valid_source_and_seeds_reviews(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(file_get_contents(base_path('tests/Fixtures/yandex_reviews.html')))]);

        $this->actingAs($this->user())
            ->postJson('/api/settings/source', ['url' => 'https://yandex.ru/maps/org/test/99/'])
            ->assertOk()
            ->assertJsonPath('data.ratingsCount', 1234)
            ->assertJsonPath('data.reviewsCount', 567)
            ->assertJsonPath('data.reviewsStored', 3);

        $this->assertDatabaseHas('organizations', ['business_id' => '99', 'name' => 'Тестовое Кафе']);
        $this->assertDatabaseCount('reviews', 3);
        Queue::assertPushed(ParseOrganizationReviews::class);
    }

    public function test_rejects_invalid_source(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/settings/source', ['url' => 'https://example.com/x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_reviews_paginate_fifty_per_page(): void
    {
        $org = Organization::create(['business_id' => '5', 'source_url' => 'u', 'parse_status' => 'ok']);
        foreach (range(1, 60) as $i) {
            $org->reviews()->create(['review_id' => "r$i", 'author' => "A$i", 'rating' => 5, 'reviewed_at' => now()->subDays($i)]);
        }

        $this->actingAs($this->user())
            ->getJson("/api/organizations/{$org->id}/reviews?page=1")
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.total', 60)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_lists_all_parsed_organizations(): void
    {
        Organization::create(['business_id' => '1', 'source_url' => 'a', 'name' => 'A', 'parse_status' => 'ok']);
        Organization::create(['business_id' => '2', 'source_url' => 'b', 'name' => 'B', 'parse_status' => 'ok']);

        $this->actingAs($this->user())
            ->getJson('/api/organizations')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_fresh_cache_hit_returns_cached_without_reparse(): void
    {
        Queue::fake();
        $org = Organization::create([
            'business_id' => '99', 'source_url' => 'https://yandex.ru/maps/org/test/99/',
            'name' => 'Cached', 'ratings_count' => 10, 'reviews_count' => 5,
            'parse_status' => 'ok', 'parsed_at' => now()->subHour(),
        ]);
        $org->reviews()->create(['review_id' => 'r1', 'author' => 'A', 'rating' => 5, 'reviewed_at' => now()]);

        $this->actingAs($this->user())
            ->postJson('/api/settings/source', ['url' => 'https://yandex.ru/maps/org/test/99/'])
            ->assertOk()
            ->assertJsonPath('cached', true)
            ->assertJsonPath('data.name', 'Cached');

        Queue::assertNotPushed(ParseOrganizationReviews::class);
    }
}
