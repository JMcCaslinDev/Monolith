# Monolith

Everything in one place: internal tools, one domain, one AWS bill.

PHP + Tailwind + Alpine.js, MariaDB, Auth0 login, permission gates on every route, audit events on every action. Deployed to AWS via Pulumi.

## Docs

| Doc | Contents |
|-----|----------|
| [docs/ACCOUNT_SETUP.md](docs/ACCOUNT_SETUP.md) | **Start here** — Auth0 + AWS account setup |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | System design |
| [docs/PERMISSIONS.md](docs/PERMISSIONS.md) | Roles, grants |
| [docs/EVENTS.md](docs/EVENTS.md) | Audit log |
| [docs/AWS.md](docs/AWS.md) | Deploy runbook |

## Quick start (local)

### 1. Accounts (you do this)

Follow **[docs/ACCOUNT_SETUP.md](docs/ACCOUNT_SETUP.md)** to create Auth0 and AWS accounts, then fill `.env`.

### 2. Run (pnpm + MariaDB — no Docker)

```bash
composer install
pnpm install
pnpm db:start      # MariaDB 11 via spindb (native, no Docker/Homebrew)
pnpm db:setup      # create user + run migrations
pnpm dev           # terminal 1 — Vite (optional if you ran pnpm build)
pnpm dev:php       # terminal 2 — PHP on :8000
```

Run `pnpm build` once so styles work without the Vite dev server.

If port 3306 is already in use, stop the other MySQL/MariaDB service first, or run `pnpm db:status` and update `DB_PORT` in `.env`.

### 3. Login

Visit http://localhost:8000/login → Auth0 → redirected to dashboard.

First user (or `BOOTSTRAP_ADMIN_EMAIL`) gets `owner` role.

## What's built

| Route | Permission | Description |
|-------|------------|-------------|
| `/` | — | Landing or dashboard |
| `/login` | — | Auth0 redirect |
| `/tools/json-converter` | `tools.json-converter.use` | Format/minify/validate JSON |
| `/admin/events` | `admin.events.view` | Audit log |
| `/admin/users` | `admin.users.manage` | Assign roles |
| `/health` | — | JSON health check |

## Scripts

```bash
pnpm db:start     # Start MariaDB (spindb)
pnpm db:setup     # User + migrations
pnpm db:migrate   # Re-run SQL migrations
pnpm db:stop      # Stop MariaDB
pnpm dev          # Vite HMR
pnpm dev:php      # PHP built-in server :8000
pnpm build        # Production assets → public/build/
php scripts/check-permissions.php   # Verify owner role seeds
cd infra && pnpm up   # Deploy AWS (after account setup)
```

## Deploy to AWS

1. Complete [docs/ACCOUNT_SETUP.md](docs/ACCOUNT_SETUP.md)
2. `cd infra && pulumi up`
3. `docker build -t monolith . && docker push <ecr-url>:latest`
4. Run SQL migrations against RDS
5. Set Auth0 env vars on ECS task (extend Pulumi or use Secrets Manager)
6. Point DNS at ALB

## Stack

PHP 8.3 · MariaDB 11 · Tailwind 3 · Alpine 3 · Auth0 · Pulumi · ECS Fargate · RDS
