# Packages

Internal projects live under `packages/{name}/`. Each package is self-contained but shares the main app's database and auth.

## Layout

```
packages/tools/
  manifest.php    # project meta, permissions, routes, events
  routes.php      # HTTP handlers (merged into routes/web.php)
  views/          # package views (rendered via package_view())
```

Core system code stays in `app/`, `resources/views/admin/`, etc. Admin is **not** a package.

## Adding a package

1. Create `packages/{id}/manifest.php` with:
   - `project` — dashboard card (`view` + `open` permissions)
   - `permissions` — all permission rows for this package
   - `routes` — registry metadata
   - `events` — event types used inside the package

2. Add `routes.php` with handlers using `dispatch()` and `package_view()`.

3. Migration — seed new permission rows + role assignments.

4. Run `php scripts/check-registry.php`.

## Project permissions

Each project uses two top-level permissions:

| Permission | Purpose |
|------------|---------|
| `projects.{id}.view` | Show on dashboard |
| `projects.{id}.open` | Enter the project |

Internal features use their own permissions (e.g. `tools.json-converter.use`).
