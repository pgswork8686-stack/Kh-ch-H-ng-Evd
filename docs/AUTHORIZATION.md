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

## Required behavior
 
Forbidden access returns HTTP 403 with a stable error code. Collection requests return only authorized resources; direct resource requests must independently verify scope and must not trust a prior list response or frontend state. Audit denied privileged actions without recording credentials or complete provider payloads.

