# Database schema

Table names use the active WordPress prefix followed by `ezev_`.

## Core tables

| Table | Business key / purpose | Important relationships |
| --- | --- | --- |
| `ezev_organizations` | `org_code` unique | organization root |
| `ezev_sites` | `site_code` unique | `organization_id` -> organizations.id |
| `ezev_org_members` | `(organization_id, user_id)` unique | WordPress user membership and `role_key` |
| `ezev_member_site_access` | `(member_id, site_id)` unique | membership scope |
| `ezev_member_station_access` | `(member_id, station_post_id)` unique | transitional post-ID station scope |
| `ezev_saved_stations` | `(user_id, station_post_id)` unique | transitional saved station relation |
| `ezev_invitations` | hashed invitation token | organization, email, role, expiry |
| `ezev_audit_logs` | append-only numeric ID | actor, action, object, JSON context, hashed IP |

Station master data is currently stored as `ezev_station` posts plus `_ezev_*` post metadata. The public key is `_ezev_station_id`; coordinates, connector types, power, demo state, organization, and site are metadata. A migration must introduce stable station keys into access and saved-station tables before post IDs can be retired from public behavior.

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

Both plugins call `dbDelta()` on activation and store their declared plugin version in an option. There is no upgrade runner on normal plugin boot, no ordered migration registry, and no rollback strategy. Future schema changes must use idempotent, versioned migrations and tests against both clean install and upgrade fixtures.

Database foreign keys are not declared. Until a deliberate foreign-key policy is adopted, application services must validate referenced stable IDs and handle deletion/orphan cleanup transactionally.
