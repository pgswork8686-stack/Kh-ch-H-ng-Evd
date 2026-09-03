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
| `ezev_chargers` | `charger_id` unique | stable `station_id`; connector data currently embedded |
| `ezev_sessions` | `session_id` unique | stable station and charger IDs |
| `ezev_energy` | numeric row ID | station/time samples; no idempotency key yet |
| `ezev_alerts` | `alert_id` unique | station and optional charger IDs |
| `ezev_maintenance` | `ticket_id` unique | station, charger, optional WordPress assignee |
| `ezev_integrations` | numeric configuration ID | encrypted credentials, mapping/settings JSON |
| `ezev_sync_logs` | append-only numeric ID | optional integration, level/event/context |

The required connector domain is not fully normalized: `connector_id` and connector type live on the charger row. A separate connector table is required if chargers can have multiple independently addressable connectors.

## Installation and migrations

Core defines schema version `1.1.0`, runs an idempotent upgrade check during normal boot, applies `dbDelta()`, backfills stable business IDs, then updates `ezev_core_db_version`. Plugin release version and schema version are deliberately independent. Future schema changes must remain idempotent and be tested against both clean-install and upgrade fixtures.

Database foreign keys are not declared. Until a deliberate foreign-key policy is adopted, application services must validate referenced stable IDs and handle deletion/orphan cleanup transactionally.
