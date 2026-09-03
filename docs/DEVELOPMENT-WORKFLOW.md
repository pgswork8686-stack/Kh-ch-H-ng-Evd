# Development workflow

## Branches

- `main`: stable, tested releases only.
- `integration/ezev-v1`: combined backend/frontend milestones.
- `codex/core-system`: Core and Operations development.
- `antigravity/frontend`: frontend-owned theme work.

The repository was empty when this backend baseline was created. Confirm remote branch protection and the frontend branch before the first integration merge.

## Change sequence

1. Rebase or merge the latest relevant integration state without rewriting other owners' work.
2. Document contract or architecture changes before implementation.
3. Implement in the owning plugin; keep theme changes narrowly integration-focused.
4. Run syntax/static checks, clean-install and upgrade migrations, API contract tests, authorization tests, and relevant UI/integration checks.
5. Verify persistence after reload and negative/error paths.
6. Commit a focused logical change and describe contract/migration impact.
7. Push `codex/core-system`; merge only stable milestones into `integration/ezev-v1`.

## Minimum test matrix

- Station CRUD, Maps search/drag/save, normalized list/detail API, filtering, and demo labeling.
- Customer cookie login, route redirect, save/unsave by stable station ID, and persistence.
- Organization owner, site manager, station operator, finance, viewer, partner, and internal positive/negative scope cases.
- Direct unauthorized resource requests return 403 even when UI links are absent.
- Provider normalization, secrets handling, signed webhooks, retry/error behavior, and idempotent sync.
- Plugin activation on clean WordPress and upgrade from the last released database version.

## Release integrity

Plugin header, runtime constant, readme stable tag, package filename, database migration version, and Git tag must agree. The observed `v4.0.2.zip` / `4.0.1` mismatch blocks release labeling until resolved.
