# Repository instructions

These rules apply to the whole repository.

## Boundaries

- Plugins own data, business logic, authorization, and APIs. The theme owns presentation and frontend UX.
- `ezev-core` owns users, organizations, memberships, sites, stations, access scopes, saved stations, invitations, Maps configuration, and the public Core API.
- `ezev-operations` owns chargers, connectors, sessions, energy, alerts, maintenance, provider integrations, and operational APIs.
- Do not change files in `wp-content/themes/ezev-theme` unless an API integration, security, or critical correctness fix requires it. Preserve the frontend team's work.
- Use stable domain identifiers in public contracts. WordPress post IDs are implementation details.

## Contracts and security

- Treat `docs/API-CONTRACT.md` as a hard contract. Document changes before implementation and preserve compatibility where reasonable.
- Enforce authorization server-side with role, organization, and resource scope. Return 403 for forbidden resources.
- Require capability checks, nonces for state-changing browser actions, sanitization, escaping, prepared queries, and protected secrets.
- Never commit production keys or present demo/manual data as live data.

## Workflow

- Backend work uses `codex/core-system`; integration uses `integration/ezev-v1`; `main` only receives tested integration milestones.
- Use focused commits such as `feat(core):`, `feat(operations):`, `fix(core):`, `docs:`, and `test:`.
- Update architecture, schema, authorization, integration, and API documents with the corresponding change.
- A feature is complete only when persistence, API behavior, authorization, reload behavior, error handling, security checks, tests, and documentation are addressed.
