# EZEV / EVD platform

This repository is the source of truth for the EZEV EV-charging platform. WordPress currently hosts the domain APIs, identity integration, station master data, operations adapter layer, and presentation theme.

## Layout

- `wp-content/plugins/ezev-core`: identity support, organizations, sites, station master data, access scopes, saved stations, Maps configuration, and `/ezev/v1` APIs.
- `wp-content/plugins/ezev-operations`: chargers, sessions, energy, alerts, maintenance, provider adapters, and operations APIs.
- `wp-content/themes/ezev-theme`: presentation only; owned by the frontend stream and not present in this initial backend baseline.
- `docs`: architecture, contracts, schema, authorization, integrations, and workflow.

## Current baseline

The audited import declared version `4.0.1` although its packages were named `v4.0.2`. Core development continues from synchronized source version `4.1.0` with database schema version `1.1.0`. See [PROJECT-CONTEXT.md](docs/PROJECT-CONTEXT.md).

Development happens on `codex/core-system`, integration on `integration/ezev-v1`, and stable releases on `main`. Never commit feature work directly to `main`.
