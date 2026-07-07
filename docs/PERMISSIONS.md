# Permissions

## Principles

- Every protected route checks a permission before running handler logic.
- Permissions are strings: `resource.action`.
- Roles bundle permissions; grants override per user when needed.
- Future billing maps **plans → permission sets** — no tool code changes.

## Naming convention

```
{resource}.{action}
```

Examples:

| Permission | Meaning |
|------------|---------|
| `tools.json-converter.use` | Open and use the JSON tool |
| `pages.dashboard.view` | See the home dashboard |
| `admin.users.manage` | Invite, disable, assign roles |
| `admin.events.view` | Read the audit log UI |
| `admin.permissions.manage` | Role matrix, grants, access control UI |
| `billing.subscription.active` | Paid tier (future) |

## Default roles

| Role | Intended for | Example permissions |
|------|--------------|---------------------|
| `owner` | Account creator / you | All permissions |
| `member` | Coworker with tool access | `pages.dashboard.view`, `tools.*.use` |
| `viewer` | Read-only | `pages.dashboard.view` |
| `admin` | Platform operator | `admin.*` |

## Database tables

Defined in `database/migrations/001_initial.sql`:

- `permissions` — registry of known permission names
- `roles` — named role buckets
- `role_permission` — role → permission
- `user_role` — user → role (add `organization_id` when multi-tenant orgs land)
- `grants` — per-user permission override with optional `expires_at`

## Check flow

```
1. Resolve authenticated user (Auth0 → local users row)
2. Load roles + grants
3. PermissionService::can($userId, 'tools.json-converter.use')
4. Denied → HTTP 403 + event permission.denied
5. Allowed → run handler + emit domain event
```

## Adding a new permission (checklist)

See **`docs/REGISTRY.md`** for the full workflow. Short version:

1. Add to `config/registry.php`.
2. Migration: `permissions` row + `role_permission` for owner (and other roles).
3. Protect route with permission middleware.
4. Emit events on state changes.
5. Run `php scripts/check-registry.php`.

## Monetization (future)

A `plan` is a named set of permission ids. On subscription change, swap the user's role or sync `grants`. Tools only call `can()` — they never know about Stripe.
