# Monolith

Permission-gated internal tools platform. See `docs/ARCHITECTURE.md`.

- PHP 8.3, MariaDB, Tailwind, Alpine, Auth0, Pulumi on AWS
- pnpm for all Node dependencies
- Every route: permission check. Every mutation: audit event.
