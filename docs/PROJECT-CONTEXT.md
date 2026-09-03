# Project context and baseline audit

Audit date: 2026-09-03

## Evidence inspected

- Loose `ezev-core` and `ezev-operations` source folders.
- Packages named `ezev-core-v4.0.2.zip` and `ezev-operations-v4.0.2.zip`.
- `EZEV-WORDPRESS-FULL-PACKAGE-v1.0.0.zip`, including its v1 theme and documentation.
- Empty Git checkout connected to `pgswork8686-stack/Kh-ch-H-ng-Evd`.

The remote exposed no `main` head during the audit. The local checkout reported `origin/main [gone]` and no commits. The plugin packages named v4.0.2 contain code whose plugin headers, constants, and readmes declare v4.0.1. This branch therefore imports the inspected v4.0.1 code as a traceable baseline; it does not claim that a v4.0.2 release exists.

## Implemented baseline

- Core activation installs organization, site, membership, scope, saved-station, invitation, and audit tables; registers the station post type; seeds demo stations; creates frontend pages; and flushes rewrites.
- Operations activation installs operational/integration tables, seeds manual demo data when empty, and schedules a five-minute WordPress cron event.
- Core exposes public station listing, cookie-based frontend login/logout, current-user context, and saved-station endpoints.
- Operations exposes scoped read endpoints and a generic HTTP provider with field mapping.
- Core includes Google Maps admin and public JavaScript, encrypted map-key storage, Places/geocoding workflows, and browser geolocation.

## Critical technical debt

1. Public station identity is inconsistent. List responses contain `station_id`, but saved-station routes accept numeric WordPress post IDs and `/me` exposes `allowed_station_post_ids`.
2. `GET /ezev/v1/stations/{station_id}` is missing.
3. The public station list is unscoped by design, while business/partner-specific station retrieval is not exposed as a dedicated scoped Core endpoint.
4. Operations authorization only requires login; users with no operational capability can call endpoints and receive empty or scoped data. Capability policy must be made explicit.
5. Webhooks accept unsigned payloads when an integration has no secret, and the full decoded payload is written to logs. Production integrations must fail closed and redact sensitive fields.
6. Generic OAuth2 handling is not OAuth2: it sends client credentials as custom headers and does not obtain, cache, expire, or refresh access tokens.
7. Provider reads can make four remote requests per overview call and do not expose a normalized `data_source`, `data_mode`, or `last_updated` envelope consistently.
8. Energy sync is append-only without an idempotency key, so repeated syncs can duplicate readings.
9. Core scope tables store `station_post_id`; stable `station_id` should become the durable relationship key through a documented migration.
10. Tables rely on logical relationships but declare no database foreign keys. Deletion and orphan-cleanup behavior is not implemented.
11. Activation automatically publishes demo pages/data. Production activation needs an explicit demo-data policy and environment guard.
12. Roles are removed and recreated during activation, which can discard manually added capabilities.
13. No automated test suite, CI workflow, migration test, WordPress test fixture, or API contract test was present.
14. No independent token authentication exists. Cookie authentication is appropriate only for the current WordPress frontend; mobile/web token work remains a future security design task.
15. The archived v1 theme contains a static map image, hard-coded pins, fallback station availability, and non-persisting forms. It must not be treated as satisfying the required real integration flows.

## Release decision

This baseline is suitable for documentation and controlled hardening, not production deployment. Priority order: stabilize IDs and API contracts; enforce capability/scope policy; harden webhooks/providers; make sync idempotent; add migrations and tests; then integrate with the frontend branch.

## Core implementation progress

- Core 4.1.0 / schema 1.1.0 introduces stable organization, site, membership, and station relationships with upgrade backfill.
- Station list/detail/create/update and saved-station contracts now use stable station IDs and normalized domain responses.
- Authenticated portal station routes enforce membership/resource scope and direct forbidden access returns HTTP 403.
- Google Maps uses live Maps JavaScript, Places, Geocoding, Advanced Marker drag, and browser geolocation with no static map fallback.
- The demo fixture contains exactly 20 VN, 20 PH, and 20 CN records; all 60 are explicitly demo/manual records and the importer forces `is_demo=true`.

Core cannot be declared production-PASS until clean-install/upgrade, REST, RBAC, persistence, and live Maps tests run against an actual WordPress/MySQL environment.
