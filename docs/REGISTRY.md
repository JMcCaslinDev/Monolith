# Registry (permissions, routes, events)

**Core:** `config/registry.php` (system admin, dashboard, auth events).

**Packages:** `packages/*/manifest.php` — merged automatically into the registry.

```bash
php scripts/check-registry.php
php scripts/check-permissions.php
php scripts/check-coverage.php
```

## Checklist — new package (internal project)

1. **`packages/{id}/manifest.php`** — `project`, `permissions`, `routes`, `events`.
2. **`packages/{id}/routes.php`** — handlers; use `package_view()` for views.
3. **Migration** — `INSERT` permissions; assign to roles.
4. **`event_summary()`** in `app/bootstrap.php` for new event types.
5. Run checks.

```bash
composer test
php scripts/check-registry.php
```

See **`.cursor/skills/unit-tests/SKILL.md`** when writing tests.

See **`packages/README.md`** for layout.

## Checklist — feature inside a package

1. Add permission(s) to the package `manifest.php`.
2. Migration seed for new permission rows.
3. Route in package `routes.php` behind `perm:` middleware.
4. Emit events on state changes.

## Admin (core, not a package)

- **`/admin`** — hub (profile dropdown → Admin).
- **`/admin/events`**, **`/admin/users`**, **`/admin/permissions`** — sub-pages.

## Navbar

Users pin openable projects on **Profile → Navbar**. Stored in `user_settings.navbar_projects`.

## Config wiring

| File | Role |
|------|------|
| `config/registry.php` | Core + merged packages |
| `config/permissions.php` | Flat permission names |
| `app/Projects/Registry.php` | Package loader |
| `scripts/check-registry.php` | Registry ↔ DB sync |
| `scripts/check-coverage.php` | Routes, mutations, events ↔ code |
