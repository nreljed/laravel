# Dependency Upgrade Process

This document describes how dependencies are kept up to date and how major upgrades are handled. It complements `renovate.json`.

## Tiers

### Tier 1 — Patch & minor updates (automated)
- Renovate opens PRs on the schedule defined in `renovate.json` (Monday 06:00 UTC).
- Patch updates auto-merge when CI is green.
- Minor updates to dev dependencies auto-merge; minor updates to production dependencies open a PR for manual review.
- All non-major updates are grouped into a single weekly PR to reduce noise.
- `minimumReleaseAge: 3 days` prevents adopting a brand-new broken release.

### Tier 2 — Major updates (manual, deliberate)
Major version bumps (Laravel, Inertia, React, Vite, TypeScript, etc.) **never auto-merge**. They are opened as individual PRs flagged with `needs-migration`.

When a major PR lands, follow this checklist before merging:

1. **Read the migration guide** for the library (Laravel → `laravel.com/docs/upgrade`, Inertia → migration guide, React → upgrade guide).
2. **Fetch current docs** with the `context7` MCP / `find-docs` skill before writing any code — do not rely on training data for API signatures.
3. **Create a branch** from `main` and bump only that dependency.
4. **Fix compile errors** — `npm run typecheck` and `php artisan test` surface removed/renamed APIs.
5. **Adopt new APIs where beneficial** — new hooks, new Inertia features, new Laravel helpers. This is a deliberate choice, not an auto-rewrite.
6. **Run the full suite**: `npm run typecheck`, `npm run lint`, `npm run build`, `php artisan test`, `vendor/bin/pint --test`.
7. **Deploy to staging** (or use the maintenance-mode window) and verify manually.
8. **Merge**, let `deploy.yml` ship it, and verify production.

### Renovate does NOT know about new APIs
Renovate only bumps version numbers and runs CI. A green CI run means "nothing broke," not "you are using the latest best practices." Adopting new APIs is a human/agent decision made during a major upgrade using the steps above.

### How the agent helps
When performing a major upgrade, the agent should:
- Use `context7` to fetch the current API for the new version before writing code.
- Read the framework's official upgrade guide.
- Apply changes incrementally, running typecheck/tests after each batch.
- Never guess API signatures from training data.

### Vulnerability alerts
Renovate `vulnerabilityAlerts` are enabled and labeled `security`. These open immediately (not on the weekly schedule) and should be merged ASAP after CI passes.

### Dependency Dashboard
A "Dependency Dashboard" issue is auto-maintained by Renovate listing all pending updates, grouped by status. Check it weekly.

### Pinning internal packages
`nreljed/*` packages are **disabled** in Renovate — they are managed by `core-update.yml` / `shop-update.yml` via GitHub releases and `composer.lock` bumps. Never bump them via Renovate.