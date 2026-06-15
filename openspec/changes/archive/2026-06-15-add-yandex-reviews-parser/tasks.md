# Tasks

## 1. Project scaffold
- [x] 1.1 `composer create-project laravel/laravel` in `projects/arcturus`, PHP 8.3
- [x] 1.2 Install Sanctum, configure SPA auth (statefulApi, same-origin)
- [x] 1.3 Add Vue 3 + Vite + Composition API frontend, Tailwind for quick styling
- [x] 1.4 SQLite config, `.env.example`, base README skeleton
- [x] 1.5 Install `spatie/browsershot` (Chromium provisioned in docker image)

## 2. Auth (spec: auth)
- [x] 2.1 Seeder for the single user
- [x] 2.2 Sanctum login / logout / `me` endpoints
- [x] 2.3 SPA login screen + auth guard / redirect

## 3. Settings (spec: settings)
- [x] 3.1 `organizations` + `reviews` migrations
- [x] 3.2 Org URL validation rule (extract businessId, reject invalid)
- [x] 3.3 `POST /settings/source` — validate, persist, trigger Tier-1 preview
- [x] 3.4 Settings screen: input, validation feedback, save, loading/error states

## 4. Parsing core (spec: reviews-parsing)
- [x] 4.1 `YandexReviewsClient` (Tier 1): fetch card HTML, extract state blob →
      counters + average + first 50; map errors to `ParseStatus`
- [x] 4.2 Tier 2: full set via server-side `?page=N` pagination (plain HTTP,
      no browser, no captcha, no rate-limit) — headless Browsershot kept as fallback
- [x] 4.3 `ParseOrganizationReviews` queued job: run Tier 2, upsert reviews by
      `reviewId`, update org counters + `parsedAt`, persist `ParseStatus`
- [x] 4.4 Captcha / unreachable / markup-changed / empty handling + logging
- [x] 4.5 Unit tests: URL validation, state-blob extraction, JSON→model mapping
      (fixture-based, no live network in CI)

## 5. Reviews view (spec: reviews-parsing)
- [x] 5.1 `GET /organizations/{id}/reviews?page=` — 50/page from DB cache
- [x] 5.2 `GET /organizations/{id}` — average, ratingsCount, reviewsCount, status
- [x] 5.3 Reviews screen: header (avg + both counters), paginated list
      (author/date/text/rating), page switch without reload, loading/error states
- [x] 5.4 "Re-parse" affordance + parse-in-progress / partial states

## 6. Delivery
- [x] 6.1 `docker-compose.yml` (FrankenPHP app + Chromium, mem_limit, db worker)
- [x] 6.2 README: local run (docker-compose), env vars, parsing approach &
      bot-protection rationale, "given more time" notes
- [x] 6.3 Push `selimdev00/arcturus`
- [x] 6.4 Deploy isolated to `snapfrom-prod` (`/srv/arcturus`, port 3800),
      Caddy vhost, Cloudflare DNS — verify live with an arbitrary org
- [x] 6.5 Smoke test on host: page-1 + full parse of a sample org, confirm no
      captcha / graceful degradation

## 7. Archive
- [ ] 7.1 Send link to recruiter, archive change to
      `openspec/changes/archive/YYYY-MM-DD-add-yandex-reviews-parser/`
