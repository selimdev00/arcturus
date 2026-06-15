# Arcturus — Yandex Maps reviews

Laravel 11 (API) + Vue 3 (SPA). Connect a Yandex Maps organization card, then
show its rating and all available reviews. The graded core is the parser:
Yandex has no public reviews API, the page is bot-protected, and reviews load by
scroll. This app handles all three.

Live: https://arcturus.selim.services
Login is a single seeded user (see `SEED_USER_EMAIL` / `SEED_USER_PASSWORD`).

## Run locally (docker-compose)

```bash
cp .env.example .env
# set SEED_USER_EMAIL / SEED_USER_PASSWORD, optionally YANDEX_PROXY
docker compose up --build
# app on http://127.0.0.1:3800
```

Without Docker (PHP 8.3 + Node 20 + a local Chromium):

```bash
composer install && npm ci && npm run build
php artisan migrate --seed
php artisan serve            # terminal 1
php artisan queue:work       # terminal 2 (runs the full paginated parse)
```

### Key env vars

| var | meaning |
|-----|---------|
| `YANDEX_BASE_URL` | `https://yandex.com` — `.ru` blocks datacenter IPs |
| `YANDEX_REVIEW_CAP` | Yandex serves ~600 reviews max per org |
| `YANDEX_MAX_SCROLLS` | headless scroll budget to reach the cap |
| `BROWSERSHOT_CHROME_PATH` | Chromium path for the headless tier |
| `YANDEX_PROXY` | optional residential proxy (`host:port`) as a captcha mitigation |
| `SEED_USER_EMAIL` / `SEED_USER_PASSWORD` | the single login |

## How the parsing works

There is **no official Yandex reviews API** (the public reviews widget caps at
5). Data comes from the public web card. Two tiers, both against `yandex.com`:

### Tier 1 — counters + first page (no browser)
`GET /maps/org/<id>/reviews/` server-renders, in an embedded JSON state blob:
the average rating, the **ratings count and reviews count separately**, and the
first ~50 reviews. `YandexReviewsClient` fetches this over plain HTTP (works from
a datacenter IP) and reads that state. This answers "save + preview" instantly
and seeds the cache.

### Tier 2 — all ~600 reviews (server-side pagination, no browser)
In the browser the remaining pages load by scroll via
`GET /maps/api/business/fetchReviews`, which returns clean JSON but is **signed
per page with an `s` parameter computed by Yandex's own JavaScript** (a cold
backend request returns 400, the token is single-use) — so that endpoint cannot
be called directly, and driving it via a headless browser proved flaky and
rate-limited from a datacenter IP.

The key finding: **the server-rendered card itself paginates with `?page=N`**
(50 reviews per page, in the same embedded state as page 1). So Tier 2 just
walks `?page=1..12` over plain HTTP, extracts each page's 50 reviews, and stops
at the first empty page — Yandex's ~600 cap. This is reliable, fast, needs no
browser/Chromium, hits no captcha, and isn't rate-limited the way the signed XHR
is. Runs as a queued job (`ParseOrganizationReviews`); reviews are upserted by
`reviewId`.

A headless fallback (`YandexReviewsScraper`, Browsershot — intercepts the signed
`fetchReviews` JSON while scrolling) is kept in the codebase for the case where
Yandex drops `?page` support; it needs Chromium + `npm install puppeteer@22` and
is off by default to keep the image lean.

### Caching & pagination
A parse stores the organization (average, both counters, `parsed_at`) and its
reviews once. The SPA paginates **50 per page from the database** — the parser is
never re-run per page view. Re-parse is explicit.

### Bot protection
Yandex's SmartCaptcha is driven mainly by IP reputation and request frequency.
Mitigations in place: target `yandex.com` (datacenter-friendly), realistic
headers, paced scrolling, results cached so live parsing is rare. For hostile
IPs the headless tier can route through a residential proxy (`YANDEX_PROXY`).
Every failure mode is distinct — `unreachable`, `markup_changed`, `empty`,
`captcha` — surfaced in the UI, never failing silently.

## Architecture notes

- Parsing lives in `app/Services/Yandex/*` (URL parsing, Tier 1 client, Tier 2
  scraper, DTOs) — controllers stay thin.
- `app/Jobs/ParseOrganizationReviews` runs the full parse off the request.
- SQLite + migrations keep the prototype single-container; the queue uses the
  database driver (no Redis).
- Vue 3 Composition API, same-origin Sanctum cookie auth.
- Tests (`php artisan test`) cover URL validation, state extraction, error
  mapping, auth guard, and cached pagination — fixture-based, no live network.

## Given more time

- Reconcile Tier-1 DOM as a backup path if the state blob shape changes.
- Periodic background refresh on a stale-TTL instead of manual re-parse.
- Exceed Yandex's ~600 cap by merging multiple sort orders (by date / rating).
- Multi-organization management and per-review business replies.
- A managed residential-proxy pool with health checks for at-scale parsing.
