# Integrations

## Google Maps

EZEV Core owns Maps configuration and loads Google Maps JavaScript, Places, and Geocoding services for station administration and public discovery. Configuration includes encrypted API key, Map ID, default country bias, center, and zoom. The browser connection test succeeds only after both the Maps JavaScript library and a reverse-geocoding request respond successfully. Keys must be restricted in Google Cloud by API and allowed HTTP referrer.

The Add Station editor provides address autocomplete, forward geocoding, an Advanced Marker with drag enabled, and synchronized latitude/longitude fields. Publishing writes the station record and metadata through Core; the public REST collection reads from the same record source. Public discovery supports Places search and browser geolocation and renders a setup/error state if Google cannot load.

The archived v1 theme's `map-demo.jpg` and hard-coded pins are visual placeholders and are not an acceptable integration. Frontend work must consume Core's real station payload and Maps configuration.

## Charging providers

`EZEV_Operations_Provider` defines connection testing and normalized fetches for chargers, sessions, energy, and alerts. The manual provider reads local tables. The generic HTTP provider supports configurable endpoints, payload roots, and field maps.

Known limitations:

- OAuth2 token acquisition/refresh is not implemented.
- Responses are validated against required field definitions and type casts before storage in the normalized local DB.
- Webhooks enforce fail-closed replay protection:
  - Missing integration secret returns `401 missing_secret`.
  - Missing or malformed timestamp returns `401 missing_timestamp`.
  - Timestamp skew > 300s returns `401 replay_rejected`.
  - Invalid HMAC signature returns `401 invalid_signature`.
  - Atomic deduplication is keyed by `integration_id` + event fingerprint (`X-EZEV-Event-ID` or payload hash).
  - Duplicate deliveries are rejected with `409 duplicate_webhook`.
  - Database receipt storage failures immediately fail-closed with `503 receipt_storage_failure`.
  - Receipts are assigned a 24-hour retention TTL (`expires_at`) and pruned by background cron.
- Energy ingestion is idempotent via `(provider, station_id, recorded_at)` unique constraint and records `provider_record_id`.
- Data flow is strictly unidirectional: `Provider -> Validation -> Local Normalized DB -> DTO Serializer -> REST Output`. REST endpoints never proxy client calls directly to external vendor systems.


## Future operational services

A dedicated OCPP gateway may publish normalized events to Operations through authenticated queues/webhooks or a service API. Clients remain on the EZEV contract; vendor and transport details stay behind the provider boundary.
