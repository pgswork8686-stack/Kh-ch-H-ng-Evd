# Authorization

## Decision model

Access is granted only when all required dimensions pass:

```text
authenticated identity
  AND required capability/action
  AND active organization membership (when organization-owned)
  AND site/station/resource scope
```

Administrators may bypass Core resource scope through `manage_options`. Internal operational access is handled separately by the Operations policy and must not imply Core mutation rights.

## Membership roles

- `owner` / `admin`: organization-wide when no narrower site/station assignments exist.
- `site_manager`: assigned sites only.
- `operations`: operational resources in assigned scope.
- `finance`: reporting/financial resources in assigned scope; no operational mutation by implication.
- `viewer`: read-only resources in assigned scope.
- `support`: explicitly granted support actions in assigned scope.

WordPress roles select a portal and coarse capabilities; membership `role_key` and access rows define tenant/resource scope. Neither layer alone is sufficient.

## Core API enforcement

- Public station discovery uses `/stations` and `/stations/{station_id}` and contains only published public master data.
- Authenticated portals use `/me/stations` for a filtered collection and `/me/stations/{station_id}` for direct scoped access.
- A direct request for an existing station outside the caller's scope returns `ezev_station_forbidden` with HTTP 403.
- Station create/update requires `ezev_manage_stations`; authentication without that capability returns 403.
- Saved-station actions operate on stable station IDs and are user-owned records.
- Only users with `manage_options` may enter `/wp-admin`; other authenticated users are redirected to their branded portal.

## Operations API enforcement
 
- Operations read routes (`/wp-json/ezev-ops/v1/*`) enforce granular data authorization based on role tier and station scope:
  - **Administrator** (`manage_options`): Full access across all stations and all data domains (`scope: 'all'`).
  - **Internal Ops / Technical** (`ezev_internal_ops`, `ezev_internal_technical`): Telemetry, chargers, connectors, sessions, alerts, and maintenance tickets within assigned station scope. Network-wide access requires explicit `ezev_view_all_stations` capability.
  - **Business / Site Manager** (`ezev_business`, `site_manager`): Station status, chargers, connectors, sessions, and energy within assigned site/station scope. May create and view maintenance tickets.
  - **Partner** (`ezev_partner`): Commercial and operational status (chargers, connectors, sessions, energy) for contracted stations only. No internal maintenance ticket details.
  - **Investor** (`ezev_investor`): High-level aggregate metrics, utilization, and financial/performance reports only (`/overview`, `/reports/performance`). Blocked from raw session telemetry and maintenance ticket inspection.
  - **Customer** (`ezev_customer`): Strictly forbidden from all operational endpoints (HTTP 403 `rest_forbidden`).
- Station scoping relies on `EZEV_Core_Auth::allowed_station_keys(user_id)`. Non-admin users only receive data for stations they are explicitly assigned to.
- `ezev_view_internal` is a portal-routing capability only and **does not bypass** resource scope or grant blanket operational access.
- Operations mutations (manual data saves, provider activation, sync triggers) require `manage_options` or `ezev_manage_operations`.
- Webhooks require integration secret verification, timestamp window verification (±300 seconds), and atomic deduplication (`X-EZEV-Event-ID` or payload hash) backed by `webhook_receipts`. Duplicate deliveries are rejected with HTTP 409, and database failures fail-closed with HTTP 503.

## Gate 3.1 Tenancy & Authorization Integrity Rules

- **Core Reusable Authorizers:** `EZEV_Core_Auth::can_read_organization`, `can_manage_organization`, `can_read_site`, `can_manage_site`, `can_manage_membership` provide centralized, unified authorization across all Core routes.
- **Tenant Isolation / Anti-Enumeration:** Non-admin users (`ezev_customer` or other tenant members) cannot enumerate all organizations, sites, or member emails across the network. Collections return only the caller's scoped resources. Direct detail requests outside the caller's membership return HTTP 403 `forbidden`.
- **Cross-Organization Boundary Enforcement:** When assigning a Site or Station to a Membership, the referenced resource must belong to the exact same Organization. Cross-tenant assignments are rejected with HTTP 422 `cross_organization_mismatch`.
- **Invitation Claim Integrity:**
  - The recipient email recorded in the invitation must strictly match the authenticated user's normalized account email (HTTP 403 `email_mismatch`).
  - An atomic single-use conditional SQL update (`UPDATE ... SET status='accepted' WHERE token_hash=... AND status='pending'`) guarantees that concurrent requests cannot double-claim an invitation token (HTTP 409 `invitation_already_claimed`).
- **Operational Mutation Role_key Verification:** Operational mutations (creating/transitioning maintenance tickets, acknowledging/resolving alerts) cannot be authorized solely by the WP role `ezev_business`. The system enforces membership `role_key`: only `owner`, `admin`, `operations`, and `site_manager` are authorized. Members with `viewer` or `finance` roles are strictly blocked with HTTP 403 `rest_forbidden`.
- **Safe Delete / Dependency Protection:** Deletion of an Organization or Site is fail-closed. If active dependent entities exist (Sites, Stations, Memberships, pending Invitations, or member site access grants), the delete operation is rejected with HTTP 409 `resource_has_dependencies` to prevent orphaned records.

## Gate 3.2 Plugin API & Security Freeze Rules

- **Request-Aware Core Mutation Callbacks:** Route-level permission callbacks (`can_manage_organization_route`, `can_manage_site_route`, `can_manage_membership_route`, `can_manage_invitation_route`) inspect the request URL parameters (`organization_id`, `site_id`, `membership_id`) and authenticate Business Owners and Admins to manage their own tenant resources without requiring global WordPress `manage_options`.
- **Target-Specific Station Resource Mutation Authorization:** In Operations REST (`/wp-json/ezev-ops/v1/*`), mutation handlers (`acknowledge_alert`, `resolve_alert`, `create_alert_ticket`, `create_maintenance`, `update_maintenance`, `transition_maintenance`) load the target record, resolve its `station_id`, and invoke `EZEV_Operations_REST::can_manage_station_resource($user_id, $station_id)`:
  - Sequence: Load entity -> 404 (if not found) -> Extract `station_id` -> Authorize target station manage -> 403 (if forbidden) -> Validate payload -> DB mutation -> Audit -> Response.
  - A user who is `site_manager` in Tenant A and `viewer` in Tenant B CANNOT mutate alerts or tickets belonging to Tenant B's stations (strict HTTP 403 `rest_forbidden`, DB remains completely untouched).
- **Invitation Transaction Integrity (InnoDB Rollback):** `accept_invitation()` encapsulates status claim and membership creation/upgrade in an explicit database transaction (`START TRANSACTION` / `COMMIT` / `ROLLBACK`). If membership persistence or lookup fails, changes are rolled back and the invitation remains in `pending` status.
- **Membership Route Organization Consistency:** Mutating or deleting a member via `/organizations/{organization_id}/members/{membership_id}` strictly verifies that the membership belongs to the organization specified in the URL. If a mismatch occurs, the request returns HTTP 404 `not_found`.
- **Scoped Freshness Calculation:** `calculate_freshness()` accepts the caller's station scope and filters. The `MAX(timestamp)` query is scoped identically to the data query. When a dataset is empty, it returns `last_updated: null`, `freshness_seconds: null`, and `is_stale: true` rather than fabricating timestamps with `current_time()`.


## Required behavior
 
Forbidden access returns HTTP 403 with a stable error code. Collection requests return only authorized resources; direct resource requests must independently verify scope and must not trust a prior list response or frontend state. Audit denied privileged actions without recording credentials or complete provider payloads.


