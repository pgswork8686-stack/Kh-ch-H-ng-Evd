# Database schema

Table names use the active WordPress prefix followed by `ezev_`.

## Core tables

| Table | Business key / purpose | Important relationships |
| --- | --- | --- |
| `ezev_organizations` | `organization_id` unique | stable organization root; numeric `id` and `org_code` retained for migration compatibility |
| `ezev_sites` | `site_id` unique | stable `organization_ref`; numeric relations retained temporarily |
| `ezev_org_members` | `membership_id` unique | stable `organization_ref`, WordPress identity, and `role_key` |
| `ezev_member_site_access` | `(member_id, site_id)` unique | stable `membership_ref` and `site_ref` added; numeric keys retained temporarily |
| `ezev_member_station_access` | legacy composite key | stable `membership_ref` and `station_id`; post ID retained temporarily for upgrade safety |
| `ezev_saved_stations` | stable `(user_id, station_id)` | post ID retained temporarily for upgrade safety |
| `ezev_invitations` | hashed invitation token | organization, email, role, expiry |
| `ezev_audit_logs` | append-only numeric ID | actor, action, object, JSON context, hashed IP |

Station master data is currently stored as `ezev_station` posts plus `_ezev_*` post metadata. The public key is `_ezev_station_id`; coordinates, connector types, power, demo state, stable organization ID, and stable site ID are metadata. Schema migration 1.1.0 backfills stable IDs into relationships while retaining legacy numeric columns for a reversible transition. Public APIs must not expose those numeric columns.

## Operations tables

| Table | Stable key / purpose | Important relationships |
| --- | --- | --- |
| `ezev_chargers` | `charger_id` unique | stable `station_id`; physical charger EVSE |
| `ezev_connectors` | `connector_id` unique | normalized `charger_id`, `station_id`, `connector_type`, `max_power_kw`, `status`, `current_power_kw` |
| `ezev_sessions` | `session_id` unique | stable `station_id`, `charger_id`, and `connector_id` |
| `ezev_energy` | unique `(provider, station_id, recorded_at)` | idempotent provider-aware energy samples; includes `provider_record_id` |
| `ezev_alerts` | `alert_id` unique | station and optional charger IDs, status lifecycle (`open`, `acknowledged`, `resolved`) |
| `ezev_maintenance` | `ticket_id` unique | station, charger, priority, status (`open`, `in_progress`, `resolved`, `closed`), optional WordPress assignee |
| `ezev_integrations` | numeric configuration ID | encrypted credentials, mapping/settings JSON |
| `ezev_sync_logs` | append-only numeric ID | optional integration, level/event/context |
| `ezev_webhook_receipts` | unique `dedup_hash` | atomic replay deduplication with `integration_id`, `event_id`, `created_at`, and `expires_at` retention TTL |

The connector domain is fully normalized into `Station → Charger → Connector → Session`. Multiple physical connectors are linked to each charger via `charger_id` and `station_id`.

## Installation and migrations

- **Core**: Defines schema version `1.1.0`, runs an idempotent upgrade check during normal boot, applies `dbDelta()`, backfills stable business IDs, then updates `ezev_core_db_version`.
- **Operations**: Defines `EZEVO_DB_VERSION` = `1.2.0` independent of plugin release version. `maybe_upgrade()` runs on boot and activation, ensures `webhook_receipts` with `expires_at`, creates `ezev_connectors`, ensures `connector_id` on sessions, ensures unique index `provider_station_time (provider, station_id, recorded_at)` and `provider_record_id` on energy, and executes legacy connector migration. No demo data is seeded on activation; Demo Import is explicit. Periodic cron cleans up expired webhook receipts.

Database foreign keys are not declared. Until a deliberate foreign-key policy is adopted, application services must validate referenced stable IDs and handle deletion/orphan cleanup transactionally.

