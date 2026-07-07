# Events

## Purpose

Append-only audit log — a **history** of what happened and a future hook for **triggers** (webhooks, alerts, billing).

Every event payload is self-contained JSON: path, method, referer, plus action-specific fields.

## Payload shape (automatic)

`EventRecorder` merges this into **every** event:

| Field | Example | Notes |
|-------|---------|-------|
| `method` | `GET` | HTTP method |
| `path` | `/profile` | Request path |
| `from` | `/` | Referer path (if different) |

Plus type-specific fields below. `subject_type` + `subject_id` columns duplicate key ids for querying.

## Event types

| Type | Summary example | Extra payload |
|------|-----------------|-------------|
| `page.viewed` | Viewed /profile | `page` |
| `page.not_found` | 404 /bad-url | — |
| `permission.granted` | Allowed tools.json-converter.use | `permission` |
| `permission.denied` | Denied admin.users.manage | `permission` |
| `tool.json_converter.used` | Opened JSON converter | `tool`, `action: open` |
| `action.performed` | JSON: format (128 bytes) | `action`, optional `input_bytes` |
| `settings.theme.changed` | Theme → dark | `theme` |
| `auth.login` | Signed in | `email` |
| `admin.role.changed` | Role → member for user #2 | `role`, `via` |
| `admin.role_permission.changed` | Granted admin.events.view on admin | `permission`, `enabled` |
| `admin.grant.added` | Grant tools.json-converter.use → user #2 | `permission` |
| `admin.grant.removed` | Revoke tools.json-converter.use from user #2 | `permission` |
| `project.opened` | Opened project tools | `project` |
| `settings.navbar.changed` | Navbar pins updated | `projects` |

Event types are listed in `config/registry.php` and package manifests. Update those and `event_summary()` when adding new types.

## Client actions (`action.performed`)

Browser-only clicks POST to `/events/action` (auth + CSRF):

- `json.format`, `json.minify`, `json.validate`, `json.clear`
- `settings.theme` (with `theme` in meta)

Never logs raw input — only byte length when relevant.

## UI

Audit log shows **Summary** (human) + **Details** (full JSON). Hover Details for full payload.

## Rules

- All writes through `EventRecorder`
- Never log passwords, tokens, or raw tool input
- `/health` excluded from `page.viewed`
