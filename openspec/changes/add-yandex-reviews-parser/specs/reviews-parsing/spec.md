# reviews-parsing

## ADDED Requirements

### Requirement: Organization summary metrics
The system SHALL obtain and expose the organization's average rating, ratings
count, and reviews count as three distinct values.

#### Scenario: Summary fetched
- **WHEN** a valid org source is configured
- **THEN** the system fetches average rating, ratings count, and reviews count
  from the server-rendered card without a headless browser, and exposes them
  separately

### Requirement: Full review retrieval past pagination and bot protection
The system SHALL retrieve all reviews available for the organization (Yandex
exposes up to ~600), not only the first page, despite scroll-loaded pagination
and bot protection. Parsing logic SHALL live in a service/wrapper class, not in
controllers or routes.

#### Scenario: Full parse
- **WHEN** a full parse runs for a configured org
- **THEN** a headless browser opens the reviews card, captures the per-page
  review JSON while scrolling until the available reviews are exhausted, and
  persists each review with author, date, text, and rating

#### Scenario: Cap reached
- **WHEN** the source exposes more reviews than Yandex serves (~600)
- **THEN** the system stops at the served cap and records the count without error

### Requirement: Cached pagination
The system SHALL serve reviews to the client 50 per page from persisted data,
without re-running the parse per page request.

#### Scenario: Page navigation
- **WHEN** the client requests a reviews page
- **THEN** the system returns 50 reviews from the cache and the client switches
  pages without a full reload

### Requirement: Resilient parsing and error surfacing
The system SHALL validate inputs and handle an unreachable page, changed markup,
empty response, and captcha distinctly, surfacing loading and error states in
the UI.

#### Scenario: Captcha encountered
- **WHEN** the source responds with bot-protection (captcha)
- **THEN** the system records a `captcha` status, does not corrupt cached data,
  and the UI shows an actionable error

#### Scenario: Markup or availability failure
- **WHEN** the page is unreachable, empty, or its expected structure is missing
- **THEN** the system records the matching status and surfaces it instead of
  failing silently
