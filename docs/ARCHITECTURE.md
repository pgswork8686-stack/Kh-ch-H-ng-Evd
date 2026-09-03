# Architecture

## Locked boundaries

```text
Clients (WordPress theme, web app, mobile apps)
                         |
                 Versioned EZEV APIs
                    /            \
             EZEV Core       EZEV Operations
                 |                 |
 users--memberships--organizations--sites--stations
                                           |
                              chargers--connectors--sessions
                                           |
                                energy--alerts--maintenance
```

WordPress is currently the system host, not the domain contract. Clients consume normalized EZEV resources and must not depend on post metadata or provider-specific fields.

## Core

Core owns WordPress identity integration, organizations, memberships, sites, stations, resource scopes, saved stations, invitations, audit events, Maps configuration, and `/wp-json/ezev/v1`.

Stations currently use the `ezev_station` custom post type with normalized metadata. `organization_id`, `site_id`, `membership_id`, and `station_id` are stable business identifiers. Numeric database keys and station post IDs remain internal compatibility columns during migration and must not enter new public contracts.

## Operations

Operations owns operational records and the provider boundary. Providers return normalized charger, session, energy, and alert records. Operations joins Core only through stable `station_id`; it must not copy station master data.

High-frequency telemetry and OCPP/WebSocket workloads are explicitly outside long-term WordPress responsibilities. They may migrate to a dedicated gateway and time-series infrastructure while retaining the public EZEV domain contract.

## Authentication

The current browser phase uses WordPress users and secure WordPress auth cookies. Branded frontend routes are `/login`, `/account`, `/portal/business`, `/portal/partner`, and `/admin`; administrators retain `/wp-admin` for technical WordPress backend administration.

Future API clients require a standards-based access/refresh token design with expiry, device sessions, rotation, and revocation. No custom token implementation should be introduced without a threat model and documented protocol.

## Authorization

Every protected request is evaluated server-side using identity, capability/role, organization membership, and resource scope. UI visibility is never an authorization decision. See `AUTHORIZATION.md`.

## Maps

Core owns the Google Maps API key, Map ID, country, center, zoom, and connection diagnostics. The station workflow is Places search -> map/marker -> optional drag -> stored coordinates -> normalized API -> client map. Secrets are encrypted at rest and never committed.

## Architectural decisions

- 2026-09-03: imported the inspected plugin code into the required repository layout without importing the archived v1 theme, because frontend ownership and current branch state could not be verified.
- 2026-09-03: documented current post-ID relationships as transitional rather than silently changing contracts or data.
- 2026-09-03: retained the existing separate `ezev-ops/v1` namespace as observed behavior; convergence under a single gateway requires a versioned contract decision.
- 2026-09-03: Core 4.1.0 introduces independent schema version 1.1.0 and dual-key migration. Stable references are authoritative; legacy numeric references remain temporarily for safe upgrades.
