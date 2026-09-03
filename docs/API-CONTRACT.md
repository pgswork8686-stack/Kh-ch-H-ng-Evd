# API contract

Status: EZEV Core v4.1.0 contract.

All responses are JSON. WordPress cookie-authenticated mutations require the standard REST nonce (`X-WP-Nonce`) from same-origin browser clients. Authentication alone does not replace resource authorization.

## Core namespace: `/wp-json/ezev/v1`

### `GET /stations`

Public station-master listing. Optional query: `country`.

```json
{
  "count": 1,
  "mode": "station-master-data",
  "stations": [{
    "station_id": "EZEV-VN-DEMO-001",
    "name": "Example station",
    "description": "Public fast-charging station",
    "address": {"line": "...", "city": "Ho Chi Minh City", "region": "...", "country": "Vietnam", "country_code": "VN"},
    "location": {"lat": 10.123, "lng": 106.456},
    "connectors": ["CCS2"],
    "max_power_kw": 180,
    "ports": {"total": 4, "available": 2},
    "opening_hours": "24/7",
    "status": "active",
    "amenities": [],
    "data": {"mode": "manual", "is_demo": true},
    "ownership": {"organization_id": "EZEV-ORG-001", "site_id": "EZEV-SITE-001"},
    "public_notes": "",
    "url": "https://example.test/stations/example/",
    "thumbnail": "",
    "updated_at": "2026-09-03T08:00:00+00:00"
  }]
}
```

The public station object never exposes WordPress post IDs or raw post metadata. Consumers key exclusively by `station_id`.

### `GET /stations/{station_id}`

Public. Returns `{ "station": <station> }` using the same domain object. Unknown or unpublished IDs return 404.

### `POST /stations`

Requires `ezev_manage_stations`. Creates a published station using the domain schema and returns 201. Duplicate `station_id` returns 409.

### `PUT|PATCH /stations/{station_id}`

Requires `ezev_manage_stations`. Updates the station addressed by stable ID. `station_id` is immutable; a conflicting body ID returns 409. Invalid coordinate ranges return 422.

### `GET /me`

Requires login. Returns WordPress user identity, roles, memberships, and stable `allowed_station_ids`.

### `GET /me/stations`

Requires login. Returns only stations allowed by active membership, organization, site, and station scope.

### `GET /me/stations/{station_id}`

Requires login. Returns the scoped station, 404 when the stable ID does not exist, or 403 when it exists outside the caller's scope.

### `GET /saved-stations`

Requires login. Returns `{ "stations": [...] }`.

### `POST /saved-stations`

Requires login. Request is `{ "station_id": "EZEV-VN-DEMO-001" }`. Success response is `{ "saved": true }`.

### `DELETE /saved-stations/{station_id}`

Requires login. The path value is a stable station ID. Success returns `{ "saved": false }`.

### `POST /auth/login`

Public entry point with `username`, `password`, and optional `remember`. Establishes a WordPress auth cookie and returns `success`, `message`, `redirect_url`, `rest_nonce`, and a user summary. The nonce is used for subsequent same-origin REST mutations. This is a browser-phase endpoint, not a token API.

### `POST /auth/logout`

Requires login. Clears the WordPress session and returns a login redirect.

### Core Entity CRUD & Access Management

#### Organizations: `/wp-json/ezev/v1/organizations`
- `GET /organizations`: List organizations scoped to the authenticated caller's active memberships. Non-members receive `[]`. Only administrators or users with `ezev_view_all_stations` can view all organizations.
- `POST /organizations`: Requires `manage_options` or `ezev_manage_organizations`. Creates an organization with stable `organization_id`. Returns 201.
- `GET /organizations/{organization_id}`: Detail of an organization (403 forbidden if not member or admin).
- `PUT|PATCH /organizations/{organization_id}`: Requires `can_manage_organization_route`. Update organization.
- `DELETE /organizations/{organization_id}`: Safe delete. Rejects with 409 `resource_has_dependencies` if active sites, stations, memberships, or pending invitations exist.

#### Sites: `/wp-json/ezev/v1/sites`
- `GET /sites`: List sites scoped to caller's memberships/access. Returns `{ "sites": [...] }`.
- `POST /sites`: Requires `can_manage_site_route` (org owner/admin). Creates site with stable `site_id`. Returns 201.
- `GET /sites/{site_id}`: Site detail (403 forbidden if outside caller's scope).
- `PUT|PATCH /sites/{site_id}`: Requires `can_manage_site_route` (org owner/admin or assigned site manager).
- `DELETE /sites/{site_id}`: Safe delete. Rejects with 409 `resource_has_dependencies` if active stations or member site access grants exist.

#### Memberships & Scopes: `/wp-json/ezev/v1/organizations/{organization_id}/members`
- `GET /organizations/{organization_id}/members`: List organization members. Caller must be an org member. Member emails are masked unless caller is manager.
- `POST /organizations/{organization_id}/members`: Requires `can_manage_membership_route`. Assign member with `user_id`, `role_key`.
- `PUT|PATCH /organizations/{organization_id}/members/{membership_id}`: Requires `can_manage_membership_route`. Enforces organization consistency (membership must belong to the organization in URL; mismatch returns 404).
- `DELETE /organizations/{organization_id}/members/{membership_id}`: Requires `can_manage_membership_route`. Enforces organization consistency. Removes member and cleans up associated site/station scopes.
- `POST /memberships/{membership_id}/sites`: Grant site-level scope. Strictly enforces that the site belongs to the member's organization (cross-org assignment returns 422 `cross_organization_mismatch`).
- `POST /memberships/{membership_id}/stations`: Grant station-level scope. Strictly enforces that the station belongs to the member's organization (cross-org assignment returns 422 `cross_organization_mismatch`).

#### Invitations Lifecycle: `/wp-json/ezev/v1/organizations/{organization_id}/invitations`
- `POST /organizations/{organization_id}/invitations`: Create an invitation with stable `invitation_id` (`EZEV-INV-xxxxxxxx`), email, role, and 7-day TTL.
- `GET /invitations/{token}`: Validate invitation token publicly before acceptance.
- `POST /invitations/{token}/accept`: Authenticated user accepts invitation. Verifies that the recipient email matches the user account email (403 `email_mismatch`). Wrapped in an InnoDB transaction (`START TRANSACTION`/`COMMIT`/`ROLLBACK`): if membership creation fails, the invitation is not consumed. Uses atomic single-use query to prevent concurrent double-claim race conditions (409 `invitation_already_claimed`).
- `POST /invitations/{id}/revoke`: Cancel a pending invitation by stable `invitation_ref` or numeric ID.

## Operations namespace: `/wp-json/ezev-ops/v1`

### Collections & Query Parameters
Endpoints support pagination and filtering: `?page=1&per_page=50&station_id=...&status=...&from_date=...&to_date=...`.
Response wrapper:
```json
{
  "data": [...],
  "pagination": { "page": 1, "per_page": 50, "total": 120, "total_pages": 3 },
  "meta": {
    "source": "Manual Provider",
    "data_source": "manual",
    "data_sources": ["manual"],
    "data_mode": "manual",
    "last_updated": "2026-09-03 11:30:00",
    "fetched_at": "2026-09-03 11:45:00",
    "freshness_seconds": 900,
    "is_stale": false
  }
}
```
*Note:*
- `last_updated` is scoped strictly to the caller's authorized station scope and request filters.
- If the dataset is empty, `last_updated` and `freshness_seconds` are `null`, and `is_stale` is `true`.
- Manual and Demo providers are never considered realtime.

- `GET /overview`: High-level aggregate KPIs.
- `GET /chargers`: List chargers with filters.
- `GET /connectors`: List connectors.
- `GET /sessions`: Charging sessions (filtered by date/station/user).
- `GET /energy`: Energy telemetry samples.
- `GET /alerts`: System alerts.
- `GET /maintenance`: Maintenance tickets.

### Detail Endpoints
- `GET /chargers/{charger_id}`: Single charger detail.
- `GET /connectors/{connector_id}`: Single connector detail.
- `GET /sessions/{session_id}`: Single session detail.
- `GET /alerts/{alert_id}`: Single alert detail.
- `GET /maintenance/{ticket_id}`: Single maintenance ticket detail.

### Maintenance & Alert Mutations
- `POST /maintenance`: Create maintenance ticket.
- `PUT|PATCH /maintenance/{ticket_id}`: Update ticket fields / assignee.
- `POST /maintenance/{ticket_id}/transition`: Transition status (`open` -> `in_progress` -> `resolved` -> `closed`).
- `POST /alerts/{alert_id}/acknowledge`: Mark alert acknowledged.
- `POST /alerts/{alert_id}/resolve`: Mark alert resolved.
- `POST /alerts/{alert_id}/create-ticket`: Escalate alert directly into a maintenance ticket.

### Reports
- `GET /reports/summary`: Aggregate operational metrics over specified date range.
- `GET /reports/performance`: Investor-oriented network performance and utilization KPIs.

### Webhook Ingestion
- `POST /webhook/{integration_id}`: Authenticated provider payload receiver. Strict fail-closed atomic deduplication with 24-hour retention.

## Error policy

- 400: malformed or missing input.
- 401: authentication or signature failure.
- 403: authenticated identity lacks capability or resource scope.
- 404: stable resource does not exist or is intentionally concealed.
- 409: state conflict or duplicate business identifier.
- 422: structurally valid request violates a domain rule.
- 5xx: internal/provider failure, without secret or upstream-payload leakage.

## Compatibility rules

- Never rename established fields in place. Add fields, deprecate explicitly, and remove only in a new API version.
- Stable IDs are strings and remain opaque to clients.
- Timestamps should converge on ISO 8601 UTC; current MySQL datetime strings are observed legacy behavior.
- Operational responses must add consistent source metadata (`data_source`, `data_mode`, `last_updated`) before being described as live.

