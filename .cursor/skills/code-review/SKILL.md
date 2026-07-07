---
name: code-review
description: >-
  Review Monolith changes for design patterns, tech stack compliance, Ponytail
  simplicity, permissions/events/registry coverage, and test quality. Use after
  implementing features, before reporting a task done, or when the user asks for
  a code review, architecture check, or standards audit.
---

# Monolith code review

Run this review **before** you tell the user a feature is complete. Read `.cursor/rules/ponytail.mdc` and apply it throughout.

## Workflow

1. Identify what changed (`git diff`, files touched this task).
2. Walk the checklist below — grep/read as needed, don't guess.
3. Fix **Critical** issues before finishing; note **Suggestion** items in your summary.
4. Run verification (see [Verification](#verification)).
5. Reply with the [Review output](#review-output) template.

Skip only for pure Q&A or docs-only edits with no PHP/JS/CSS behavior changes.

## Ponytail check

Apply the lazy-senior ladder from `ponytail.mdc`:

| Question | Fail if… |
|----------|----------|
| YAGNI | New abstraction, dependency, or file without clear need |
| Reuse | Duplicated helper/logic that already exists in `app/` or `bootstrap.php` |
| Minimal diff | Boilerplate, over-engineering, or symptom-only bug fix |
| Root cause | Bug fix patches one caller but siblings still broken |
| Intentional shortcuts | Non-trivial simplification missing `ponytail:` comment with ceiling + upgrade path |

**Pass:** shortest working diff, boring over clever, deletion over addition.

## Tech stack (non-negotiable)

| Area | Required | Reject |
|------|----------|--------|
| Database | MariaDB / portable SQL (`pdo_mysql`) | Postgres-only syntax, MySQL-only hacks that break SQLite tests |
| Package manager | **pnpm** (root + `infra/`) | npm, yarn, bun for this repo |
| UI | Tailwind + Alpine, server-rendered PHP views | React, Vue, SPA frameworks unless explicitly requested |
| Auth | Auth0 for identity; permissions in PHP | Auth logic only in frontend |
| PHP | 8.3, PSR-12 (`composer lint`) | New frameworks, ORMs not already in project |
| Assets | Vite via pnpm | Ad-hoc script tags for app JS/CSS |

## Design patterns

### Permissions & routes

- [ ] Every protected route declares `resource.action` permission
- [ ] Permission checks use `PermissionService` / middleware — not ad-hoc role strings
- [ ] New permissions have migration seeds + entries in `config/registry.php`
- [ ] Admin mutations use `require_admin_hub()` or equivalent permission gate

### Events

- [ ] State-changing actions call `EventRecorder` with a typed event name
- [ ] New event types listed in `config/registry.php` `events`
- [ ] Denied permission attempts record `permission.denied` where applicable

### Registry & packages

- [ ] Routes, permissions, events, mutations updated in `config/registry.php` or package `manifest.php`
- [ ] New tools follow `packages/{id}/` layout (`manifest.php`, `routes.php`, `views/`)
- [ ] Package views use `package_view()`, not raw `include`
- [ ] Core admin stays in `resources/views/admin/` — not a package

### Data & SQL

- [ ] Migrations in `database/migrations/` — numbered, idempotent where possible
- [ ] SQL portable between MariaDB and SQLite test schema (no `ON DUPLICATE KEY` without fallback pattern)
- [ ] Services accept `PDO` in constructor for testability

### Views & UI

- [ ] Views in `resources/views/` or `packages/*/views/`
- [ ] Alpine for interactivity; no inline React/Vue
- [ ] Dark-mode-safe table hovers (`.table-row-hover` in `app.css` if adding tables)

### Tests

- [ ] New behavior has a **useful** test per `.cursor/skills/unit-tests/SKILL.md`
- [ ] Tests assert behavior, not tautologies
- [ ] `composer test` green

## Verification

Run from project root after fixing review findings:

```bash
composer format
composer lint
composer test
```

If permissions, routes, or events changed:

```bash
php scripts/check-registry.php
php scripts/check-coverage.php
```

## Review output

```markdown
## Code review

**Verdict:** Pass | Pass with notes | Blocked (fixed N issues)

### Ponytail
- [one line: minimal diff / reuse / YAGNI assessment]

### Stack & patterns
- [bullet per checklist area: OK or what was wrong + fix]

### Verification
- format: pass/fail
- lint: pass/fail
- test: N tests pass/fail
- registry/coverage scripts: pass/fail/skipped

### Notes (optional)
- Suggestions not fixed in this pass
```

## Reference docs

Read when the change touches these areas:

- `docs/ARCHITECTURE.md` — stack, request lifecycle
- `docs/REGISTRY.md` — registry contract
- `docs/PERMISSIONS.md` — permission model
- `docs/EVENTS.md` — event recording
- `packages/README.md` — package layout
