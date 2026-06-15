<?php

return [
    // yandex.com — .ru blocks datacenter IPs.
    'base_url' => env('YANDEX_BASE_URL', 'https://yandex.com'),

    // Yandex exposes at most ~600 published reviews per org under one ranking.
    'review_cap' => (int) env('YANDEX_REVIEW_CAP', 600),

    // Optional explicit Chromium path for Browsershot (set in the docker image).
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH') ?: null,

    // Optional residential proxy (host:port) for the headless tier as a captcha
    // mitigation; empty = direct.
    'proxy' => env('YANDEX_PROXY') ?: null,

    // Headless scroll budget — generous enough to reach the ~600 cap.
    'max_scrolls' => (int) env('YANDEX_MAX_SCROLLS', 40),

    // Cache freshness: re-pasting an org already parsed within this window
    // returns the cached result instead of re-parsing. Reviews change slowly,
    // so a day is a sensible default; the manual "refresh" always re-parses.
    'cache_ttl_hours' => (int) env('YANDEX_CACHE_TTL_HOURS', 24),
];
