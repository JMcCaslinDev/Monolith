# Monolith Architecture

## Vision

One domain, one AWS bill, many tools. Monolith is a permission-gated internal tools platform: PHP on the server, Tailwind + Alpine in the browser, Auth0 for identity, MariaDB for data, Pulumi for infrastructure.

Every user-facing route declares a permission. Every state-changing action emits an event.

## Stack

| Layer | Choice |
|-------|--------|
| Runtime | PHP 8.3+ |
| Database | MariaDB 11 (AWS RDS) |
| UI | Tailwind CSS 3, Alpine.js 3 |
| Auth | Auth0 (OIDC) |
| Assets | Vite (pnpm) |
| IaC | Pulumi (TypeScript, pnpm) |
| Hosting | AWS: ALB + ECS Fargate + RDS MariaDB |

### Why MariaDB

MariaDB is a drop-in MySQL fork with compatible SQL and drivers. PHP connects via the standard `pdo_mysql` extension — no special client needed. AWS RDS offers managed MariaDB with the same operational model as MySQL, often with favorable licensing for open-source stacks.

## Request lifecycle

```
Browser
  → Route53 (yourdomain.com)
  → ALB (HTTPS)
  → ECS Fargate (PHP container)
      → Auth middleware (Auth0 session)
      → Permission middleware (can user do X?)
      → Controller / view
      → EventRecorder (audit log)
  → RDS MariaDB
```

## Directory layout

```
Monolith/
├── app/                  # Application code
│   ├── Http/             # Controllers, middleware
│   ├── Services/         # EventRecorder, PermissionService
│   └── bootstrap.php     # Autoload, env, DB connection
├── config/               # app.php, database.php, permissions.php
├── database/migrations/  # SQL migrations (MariaDB)
├── docs/                 # Design docs (you are here)
├── infra/                # Pulumi AWS stack
├── public/               # Web root (index.php, built assets)
├── resources/
│   ├── css/              # Tailwind entry
│   ├── js/               # Alpine entry
│   └── views/            # PHP templates
├── routes/web.php        # Route table
├── composer.json         # PHP dependencies
└── package.json          # pnpm — Vite, Tailwind, infra workspace
```

## Auth flow (Auth0)

1. `GET /login` redirects to Auth0 Universal Login.
2. `GET /auth/callback` exchanges the code, upserts a local `users` row keyed by `auth0_sub`.
3. Session cookie holds the authenticated user id.
4. **Authorization lives in Monolith** — Auth0 proves identity; permissions decide access.

## Local development

```bash
cp .env.example .env
docker compose up -d          # MariaDB
composer install
pnpm install
pnpm dev                      # Vite HMR for CSS/JS
php -S localhost:8000 -t public
```

Open http://localhost:8000

## Deployment

See [AWS.md](./AWS.md) for account setup, Pulumi deploy, Auth0 production URLs, and go-live checklist.

## Related docs

- [PERMISSIONS.md](./PERMISSIONS.md) — naming, roles, grants, monetization hook
- [EVENTS.md](./EVENTS.md) — audit log taxonomy and payload rules
