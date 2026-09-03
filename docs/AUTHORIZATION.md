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

## Remaining Operations gap

Operations read routes still need a dedicated capability/action matrix in the Operations milestone. Core resource scoping alone must not be treated as authorization to view every operational field.

## Required behavior

Forbidden access returns HTTP 403 with a stable error code. Collection requests return only authorized resources; direct resource requests must independently verify scope and must not trust a prior list response or frontend state. Audit denied privileged actions without recording credentials or complete provider payloads.
