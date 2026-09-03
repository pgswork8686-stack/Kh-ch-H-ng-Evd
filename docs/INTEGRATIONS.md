# Integrations

## Google Maps

EZEV Core owns Maps configuration and loads Google Maps JavaScript/Places services for station administration and public discovery. Configuration includes encrypted API key, Map ID, default country, center, and zoom. Keys must be restricted in Google Cloud by API and allowed origin; server-side diagnostic calls require an appropriate server-key restriction model.

The archived v1 theme's `map-demo.jpg` and hard-coded pins are visual placeholders and are not an acceptable integration. Frontend work must consume Core's real station payload and Maps configuration.

## Charging providers

`EZEV_Operations_Provider` defines connection testing and normalized fetches for chargers, sessions, energy, and alerts. The manual provider reads local tables. The generic HTTP provider supports configurable endpoints, payload roots, and field maps.

Known limitations:

- OAuth2 token acquisition/refresh is not implemented.
- Responses are not schema-validated before normalization.
- Retries, circuit breaking, pagination, rate limits, and cursor/checkpoint persistence are absent.
- Overview can fan out to four upstream calls.
- Webhooks fail open when no secret is configured and log decoded payloads without redaction.
- Energy ingestion lacks an upstream event key and is not idempotent.

Provider implementations must normalize vendor fields before storage or client exposure and must publish source, mode, and freshness. Manual/demo data must never be labeled realtime.

## Future operational services

A dedicated OCPP gateway may publish normalized events to Operations through authenticated queues/webhooks or a service API. Clients remain on the EZEV contract; vendor and transport details stay behind the provider boundary.
