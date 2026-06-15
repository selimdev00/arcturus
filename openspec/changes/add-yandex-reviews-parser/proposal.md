# Add Yandex Maps reviews parser

## Why

Test task (Laravel + Vue 3): build a small app that connects a Yandex Maps
organization card and shows its reviews and rating. There is no official Yandex
reviews API, so the central, graded part is the parser that must reliably pull
all available reviews (~600 cap per org) past Yandex's bot protection and
scroll-loaded pagination.

Feasibility was validated live before committing to this design:

- `yandex.com/maps/org/<id>/reviews/` returns the first 50 reviews + both
  counters (ratings count, reviews count) + average rating server-rendered in
  the initial HTML. Plain HTTP works from a datacenter IP (Hetzner Helsinki),
  no captcha, even across a 12-request burst.
- `yandex.ru` blocks datacenter IPs; `yandex.com` does not — we use `.com`.
- The internal `GET /maps/api/business/fetchReviews` returns clean JSON
  (`reviewId, author, text, rating, updatedTime, photos, videos`, 50/page) but
  is signed per-page with an `s` parameter computed by Yandex's client JS.
  Replaying or hand-crafting the URL returns 400 — it cannot be called cold from
  the backend.
- A headless browser that scrolls the reviews container loads all reviews
  (verified: 0 → 600, then it hits Yandex's ~600 cap), and the per-page
  `fetchReviews` JSON can be intercepted from inside the browser, giving clean
  structured data instead of fragile DOM parsing.

## What changes

- **Auth**: single seeded user, SPA login via Sanctum. No registration.
- **Settings**: one screen to paste an org card URL, validate it, persist it.
- **Parsing capability** (the core):
  - `page 1` (counters + average + first 50) via a plain HTTP fetch of the
    server-rendered card — fast, no browser, works from the host IP.
  - `full set (~600)` via a headless-browser job that opens the reviews card,
    intercepts the `fetchReviews` JSON while scrolling to the cap, and persists
    every review.
  - Results are cached in the DB; the SPA paginates 50/page from the cache, the
    parse job is not re-run per page view.
- **Reviews view**: average rating, ratings count and reviews count shown
  separately, paginated list (author, date, text, rating) 50 per page.
- **Resilience**: input validation, explicit handling of unreachable page,
  changed markup, empty response, and captcha; loading/error states surfaced in
  the UI.
- **Delivery**: docker-compose stack, deployed isolated on the existing host
  under `arcturus.selim.services`, README documenting the parsing approach.

## Impact

- New project `arcturus` (Laravel 11 + Sanctum API, Vue 3 SPA, SQLite,
  headless Chromium via Browsershot), new repo `selimdev00/arcturus`.
- New specs: `auth`, `settings`, `reviews-parsing`.
- Deploy: new isolated docker-compose stack on host `snapfrom-prod`
  (`/srv/arcturus`, port 3800), new Caddy vhost, new Cloudflare DNS record.
- No change to any existing stack on the host.
