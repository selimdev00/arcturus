<?php

namespace Tests\Feature;

use App\Enums\ParseStatus;
use App\Services\Yandex\YandexReviewsClient;
use App\Services\Yandex\YandexUrl;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexReviewsClientTest extends TestCase
{
    private function client(): YandexReviewsClient
    {
        return app(YandexReviewsClient::class);
    }

    private function url(): YandexUrl
    {
        return YandexUrl::tryParse('https://yandex.ru/maps/org/test/99/');
    }

    public function test_extracts_counters_and_first_reviews_from_state(): void
    {
        Http::fake(['*' => Http::response(file_get_contents(base_path('tests/Fixtures/yandex_reviews.html')))]);

        $r = $this->client()->fetchSummary($this->url());

        $this->assertSame(ParseStatus::Ok, $r->status);
        $this->assertSame('Тестовое Кафе', $r->name);
        $this->assertSame(4.7, $r->averageRating);
        $this->assertSame(1234, $r->ratingsCount);   // оценки
        $this->assertSame(567, $r->reviewsCount);     // отзывы
        $this->assertCount(3, $r->reviews);

        $first = $r->reviews[0];
        $this->assertSame('rev-1', $first->reviewId);
        $this->assertSame('Иван П.', $first->author);
        $this->assertSame(5, $first->rating);
        $this->assertSame('2026-05-01', $first->reviewedAt->toDateString());
    }

    public function test_maps_captcha_response(): void
    {
        Http::fake(['*' => Http::response(file_get_contents(base_path('tests/Fixtures/yandex_captcha.html')))]);

        $this->assertSame(ParseStatus::Captcha, $this->client()->fetchSummary($this->url())->status);
    }

    public function test_maps_unreachable_response(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->assertSame(ParseStatus::Unreachable, $this->client()->fetchSummary($this->url())->status);
    }

    public function test_maps_empty_response(): void
    {
        Http::fake(['*' => Http::response('tiny')]);

        $this->assertSame(ParseStatus::Empty, $this->client()->fetchSummary($this->url())->status);
    }
}
