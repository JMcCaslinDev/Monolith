---
name: unit-tests
description: >-
  Write PHPUnit unit tests for Monolith (PHP). Use when adding features,
  fixing bugs, or when the user asks for tests, coverage, or test quality.
  Enforces meaningful behavioral tests — not surface-level getters or tautologies.
---

# Monolith unit tests

Read before writing any test. Run `composer test` when done.

## What makes a test useful

A test must **fail if behavior breaks**. Justify every test with: *what user-visible or security behavior does this protect?*

| Write this | Not this |
|------------|----------|
| `can()` denies expired grants | `assertTrue(true)` |
| Owner cannot strip owner perms via UI | Assert class exists |
| `group_events` clusters by correlation_id | Test that PHP runs |
| Middleware records `permission.granted` | Mock everything, assert mock called |

**Reject** tests that only assert implementation details with no behavioral claim.

## Stack

- **PHPUnit 11** — `composer test` (runs `scripts/run-tests.php`, writes `var/test-status.json`)
- **SQLite in-memory** — `Tests\Support\TestCase` seeds roles/permissions/users
- **No browser tests** — unit/integration only for now

## File layout

```
tests/
  Support/TestCase.php    # SQLite + seed data
  schema.sqlite.sql       # portable schema
  Unit/                   # one class per service/area
  Integration/            # shell scripts, DB-dependent checks
```

## TestCase pattern

```php
namespace Tests\Unit;

use App\Services\PermissionService;
use Tests\Support\TestCase;

final class PermissionServiceTest extends TestCase
{
    public function test_member_cannot_access_admin_without_grant(): void
    {
        $memberId = $this->insertMember();
        $perms = new PermissionService($this->db);
        $this->assertFalse($perms->can($memberId, 'admin.events.view'));
    }
}
```

Pass `$this->db` into services — do not use global `db()` in unit tests.

## What to test per layer

| Layer | Focus |
|-------|--------|
| `PermissionService` | can/allForUser, grants, roles, breakdown, owner guard |
| `EventRecorder` | payload merge, correlation_id, persistence |
| `UserSettingsService` | get/set, navbar intersection |
| `Registry` | package manifests, visible/openable filtering |
| `Middleware` | grant path records event (deny calls `exit` — don't test via Middleware) |
| `bootstrap.php` helpers | `group_events`, `event_summary`, `has_admin_access` |
| Scripts | `check-coverage.php` passes (integration) |

**AuthService** — skip Auth0 HTTP; test via integration later if needed.

## Adding tests for new features

1. New permission? → test `can()` + role matrix seed in migration matches registry check scripts.
2. New route mutation? → test service method + add row to `config/registry.php` `mutations`.
3. New event type? → test `event_summary()` output + registry `events` list.
4. New package? → `RegistryTest`-style assertion that manifest loads.

## Commands

```bash
composer test              # all tests + coverage summary → var/test-status.json
composer test:unit         # phpunit only
composer lint              # phpcs (PSR-12)
composer format            # phpcbf auto-fix
php scripts/check-coverage.php
```

Admin → **System status** shows last run + coverage (local: can trigger run from UI).

## Coverage goal

Aim for **meaningful coverage of `app/` services**, not 100% line coverage for vanity. Every public method with branching logic should have at least one test that would catch a real regression.

Install **pcov** or **xdebug** for coverage numbers: `pecl install pcov`
