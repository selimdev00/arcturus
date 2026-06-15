<?php

namespace App\Services\Yandex;

/**
 * Parses and normalizes a Yandex Maps organization card URL.
 *
 * Accepts full card URLs (yandex.{ru,com,by,kz,...}/maps/org/<slug>/<id>/...)
 * and id-only forms. Short share links (yandex.ru/maps/-/...) are resolved by
 * the caller via redirect before reaching here.
 */
class YandexUrl
{
    public function __construct(
        public readonly string $businessId,
        public readonly ?string $slug,
    ) {
    }

    public static function tryParse(string $url): ?self
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // Must be a yandex maps host.
        if (! preg_match('#^https?://(?:[\w-]+\.)*yandex\.[a-z.]+/maps/#i', $url)) {
            return null;
        }

        // /maps/org/<slug>/<id>/  or  /maps/org/<id>/
        if (preg_match('#/maps/org/(?:([^/]+)/)?(\d+)#', $url, $m)) {
            $slug = $m[1] !== '' ? $m[1] : null;
            // when only one path segment matched, $m[1] holds it as slug-or-id
            if ($slug !== null && ! preg_match('/^\d+$/', $m[2])) {
                return null;
            }

            return new self(businessId: $m[2], slug: $slug);
        }

        return null;
    }

    /** Server-rendered reviews card URL on yandex.com (.ru blocks datacenter IPs). */
    public function reviewsUrl(string $base = 'https://yandex.com'): string
    {
        $path = $this->slug
            ? "/maps/org/{$this->slug}/{$this->businessId}/reviews/"
            : "/maps/org/{$this->businessId}/reviews/";

        return rtrim($base, '/').$path;
    }
}
