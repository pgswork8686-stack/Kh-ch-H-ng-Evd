# Development workflow

## Branches

- `main`: stable, tested releases only.
- `develop/ezev-v1`: all active development and feature implementations.

## Change sequence

1. Ensure working directory is clean on `develop/ezev-v1`.
2. Document contract or architecture changes before implementation.
3. Implement in the owning plugin; keep theme changes narrowly integration-focused.
4. Run syntax/static checks, clean-install and upgrade migrations, API contract tests, authorization tests, and relevant UI/integration checks on runtime WordPress + MySQL.
5. Verify persistence after reload and negative/error paths.
6. Commit a focused logical change and describe contract/migration impact.
7. Push `develop/ezev-v1`; merge into `main` only for tested releases.

## Minimum test matrix

- Station CRUD, Maps search/drag/save, normalized list/detail API, filtering, and demo labeling.
- Customer cookie login, route redirect, save/unsave by stable station ID, and persistence.
- Organization owner, site manager, station operator, finance, viewer, partner, and internal positive/negative scope cases.
- Direct unauthorized resource requests return 403 even when UI links are absent.
- Provider normalization, secrets handling, signed webhooks, retry/error behavior, and idempotent sync.
- Plugin activation on clean WordPress and upgrade from the last released database version.

## Release integrity

Plugin header, runtime constant, readme stable tag, package filename, database migration version, and Git tag must agree. The observed `v4.0.2.zip` / `4.0.1` mismatch blocks release labeling until resolved.
