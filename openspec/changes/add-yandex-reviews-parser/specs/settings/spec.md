# settings

## ADDED Requirements

### Requirement: Organization source URL configuration
The system SHALL let the authenticated user submit a Yandex Maps organization
card URL, validate it, persist it, and extract the numeric business id.

#### Scenario: Valid org URL
- **WHEN** the user submits a URL matching a Yandex Maps org card
  (`yandex.{ru,com,...}/maps/org/<slug>/<id>/`) or a short share link
- **THEN** the system extracts the business id, persists the source, and
  triggers a fast preview fetch

#### Scenario: Invalid URL
- **WHEN** the user submits a URL that is not a Yandex Maps org card
- **THEN** the system rejects it with a validation error and persists nothing

#### Scenario: Save feedback
- **WHEN** a save is in progress or fails
- **THEN** the settings screen surfaces loading and error states to the user
