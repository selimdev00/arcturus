# auth

## ADDED Requirements

### Requirement: Single seeded user authentication
The system SHALL authenticate a single pre-seeded user via Sanctum SPA cookie
auth. Registration SHALL NOT be exposed.

#### Scenario: Valid login
- **WHEN** the seeded user submits correct credentials to the login endpoint
- **THEN** a Sanctum session is established and the SPA is granted access to
  protected routes

#### Scenario: Invalid login
- **WHEN** incorrect credentials are submitted
- **THEN** the system returns an authentication error and grants no session

#### Scenario: Protected routes require auth
- **WHEN** an unauthenticated request hits a protected API route
- **THEN** the system returns 401 and the SPA redirects to the login screen
