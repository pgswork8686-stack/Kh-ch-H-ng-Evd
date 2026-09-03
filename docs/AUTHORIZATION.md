# Authorization

## Decision model

Access is granted only when all required dimensions pass:

```text
authenticated identity
  AND required capability/action
  AND active organization membership (when organization-owned)
  AND site/station/resource scope
```

Administrators may bypass resource scope through `manage_options`. Internal bypass currently uses `ezev_view_internal`; this is broad and should be narrowed for operations actions.

## Membership roles

- `owner` / `admin`: organization-wide when no narrower site/station assignments exist.
- `site_manager`: assigned sites only.
- `operations`: operational resources in assigned scope.
- `finance`: reporting/financial resources in assigned scope; no operational mutation by implication.
- `viewer`: read-only resources in assigned scope.
- `support`: explicitly granted support actions in assigned scope.

WordPress roles select a portal and coarse capabilities; membership `role_key` and access rows define tenant/resource scope. Neither layer alone is sufficient.

## Current gaps

- Core's public station list intentionally exposes published station master data, but no authenticated scoped Core collection exists for business/partner portals.
- Operations read routes check only login at the permission callback. Row filtering prevents broad asset disclosure for ordinary users, but capability intent is not enforced and empty results conceal policy mistakes.
- No mutation APIs exist for station or operational resources; admin form handlers require a separate capability/nonce audit before being treated as an API authorization model.
- Saved-station actions use numeric post IDs and validate existence, not a stable domain resource key.

## Required behavior

Forbidden access returns HTTP 403 with a stable error code. Collection requests return only authorized resources; direct resource requests must independently verify scope and must not trust a prior list response or frontend state. Audit denied privileged actions without recording credentials or complete provider payloads.
