# API contract

Status: observed v4.0.1 baseline. Fields marked **transitional** must not be copied into new clients.

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
    "latitude": 10.123,
    "longitude": 106.456,
    "connector_types": ["CCS2"],
    "max_power_kw": 180,
    "data_mode": "manual",
    "is_demo": true
  }]
}
```

The station object also currently exposes `post_id`, description, address/country fields, port counts, manual status, hours, amenities, organization/site internal IDs, notes, URL, and thumbnail. `post_id` is **transitional**. Consumers must key by `station_id`.

`GET /stations/{station_id}` is required but not implemented in this baseline.

### `GET /me`

Requires login. Returns WordPress user identity, roles, memberships, and `allowed_station_post_ids` (**transitional**). A future compatible addition should provide `allowed_station_ids` using stable IDs.

### `GET /saved-stations`

Requires login. Returns `{ "stations": [...] }`.

### `POST /saved-stations`

Requires login. Current request is `{ "station_id": 123 }`, where the value is actually a WordPress post ID (**contract defect**). Current success response is `{ "saved": true }`.

### `DELETE /saved-stations/{station_id}`

Requires login. The path value is currently a numeric WordPress post ID (**contract defect**). Success returns `{ "saved": false }`.

### `POST /auth/login`

Public entry point with `username`, `password`, and optional `remember`. Establishes a WordPress auth cookie and returns `success`, `message`, `redirect_url`, and a user summary. This is a browser-phase endpoint, not a token API.

### `POST /auth/logout`

Requires login. Clears the WordPress session and returns a login redirect.

## Operations namespace: `/wp-json/ezev-ops/v1`

Observed read endpoints: `/overview`, `/chargers`, `/energy`, `/sessions`, and `/alerts`. They require login and filter records to allowed stable station keys; administrators/internal viewers receive all records. The present permission callback does not require an operations capability.

`GET /overview` returns `provider`, `scope` (`all` or `restricted`), and aggregate `data`. Collection endpoints return a single plural collection property.

`POST /webhook/{integration_id}` accepts provider payloads. If a webhook secret is configured, `X-EZEV-Signature` must equal the lowercase hex HMAC-SHA256 of the raw body. The current implementation permits unsigned requests when no secret exists; production integrations must not rely on that behavior.

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
