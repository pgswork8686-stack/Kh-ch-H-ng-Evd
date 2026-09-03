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

## Operations namespace: `/wp-json/ezev-ops/v1`

Read endpoints: `/overview`, `/chargers`, `/connectors`, `/energy`, `/sessions`, and `/alerts`.
- **Authorization**: Requires login and operational capability (`ezev_view_operations`, `ezev_view_internal`, or `manage_options`). Callers without this capability receive `403 Forbidden`.
- **Scoping**: Records are filtered to allowed stable station keys for scoped tenant users; administrators/internal viewers receive all records (`scope: 'all'`).
- `GET /overview` returns `provider`, `scope` (`all` or `restricted`), and aggregate `data` including `connectors_total`.
- `GET /connectors` returns `{ "connectors": [...] }` representing physical connector entities linked to chargers and stations.

`POST /webhook/{integration_id}` accepts provider payloads:
- **Secret requirement**: A webhook secret must be configured on the integration; requests without a secret configured return `401 missing_secret`.
- **Replay protection**: Headers `X-EZEV-Timestamp` and `X-EZEV-Signature` are required. The timestamp must be within a ±300 second window of the server's current UTC time. Requests outside this window return `401 replay_rejected`.
- **Signature verification**: `X-EZEV-Signature` must equal the lowercase hex `hash_hmac('sha256', $timestamp . '.' . $raw_body, $secret)`. Invalid signatures return `401 invalid_signature`.

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
