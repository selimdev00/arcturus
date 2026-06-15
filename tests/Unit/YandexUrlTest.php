<?php

namespace Tests\Unit;

use App\Services\Yandex\YandexUrl;
use PHPUnit\Framework\TestCase;

class YandexUrlTest extends TestCase
{
    public function test_parses_full_card_url_with_slug(): void
    {
        $u = YandexUrl::tryParse('https://yandex.ru/maps/org/gum/1331623198/');
        $this->assertNotNull($u);
        $this->assertSame('1331623198', $u->businessId);
        $this->assertSame('gum', $u->slug);
    }

    public function test_parses_reviews_subpath_and_com_host(): void
    {
        $u = YandexUrl::tryParse('https://yandex.com/maps/org/some-place/981/reviews/?ll=1,2');
        $this->assertNotNull($u);
        $this->assertSame('981', $u->businessId);
    }

    public function test_rejects_non_yandex_and_non_org_urls(): void
    {
        $this->assertNull(YandexUrl::tryParse('https://example.com/maps/org/x/1/'));
        $this->assertNull(YandexUrl::tryParse('https://yandex.ru/maps/213/moscow/'));
        $this->assertNull(YandexUrl::tryParse('not a url'));
        $this->assertNull(YandexUrl::tryParse(''));
    }

    public function test_builds_reviews_url_on_com(): void
    {
        $u = YandexUrl::tryParse('https://yandex.ru/maps/org/gum/1331623198/');
        $this->assertSame(
            'https://yandex.com/maps/org/gum/1331623198/reviews/',
            $u->reviewsUrl('https://yandex.com'),
        );
    }
}
