# Account setup — Auth0 & AWS

Do this **before** deploying. Keep credentials out of git — use `.env` locally and AWS Secrets Manager in production.

---

## Part 1: Auth0

### 1. Create tenant

1. Go to [auth0.com](https://auth0.com) → Sign up (free tier works for dev).
2. Pick a **tenant domain** (e.g. `yourname.us.auth0.com`) — you cannot change this later.
3. Choose **US** or **EU** region (match where you’ll host AWS if compliance matters).

### 2. Create application (local dev)

1. Dashboard → **Applications** → **Create Application**.
2. Name: `Monolith Local`.
3. Type: **Regular Web Application** → Create.
4. **Settings** tab — copy these into `.env`:
   - Domain → `AUTH0_DOMAIN`
   - Client ID → `AUTH0_CLIENT_ID`
   - Client Secret → `AUTH0_CLIENT_SECRET`
5. **Application URIs** (local):
   - Allowed Callback URLs: `http://localhost:8000/auth/callback`
   - Allowed Logout URLs: `http://localhost:8000`
   - Allowed Web Origins: `http://localhost:8000`
6. Save Changes.

### 3. Cookie secret

```bash
openssl rand -hex 32
```

Put result in `.env` as `AUTH0_COOKIE_SECRET` (must be 32+ bytes).

### 4. Bootstrap admin (optional)

Set in `.env`:

```
BOOTSTRAP_ADMIN_EMAIL=you@example.com
```

First login with this email gets the `owner` role. If unset, the **first user ever** becomes `owner`.

### 5. Production application (later)

Create a **second** app `Monolith Production` (or reuse one app with multiple URLs):

- Callback: `https://yourdomain.com/auth/callback`
- Logout: `https://yourdomain.com`
- Web Origins: `https://yourdomain.com`

### 6. Recommended Auth0 settings

- **Authentication** → Database → enable Username-Password (or only social/Google for work).
- **Security** → Multi-factor → enable for production.
- **Actions** → optional: block disposable emails later.

---

## Part 2: AWS

### 1. Create / sign in to AWS account

1. [aws.amazon.com](https://aws.amazon.com) → Create account (credit card required; free tier applies to some services).
2. Enable **MFA** on the root account, then create an **IAM admin user** for daily use — don’t use root for Pulumi.

### 2. IAM user for Pulumi

1. IAM → **Users** → **Create user** → name: `pulumi-deploy`.
2. Attach policy: `AdministratorAccess` (tighten to least-privilege later).
3. **Security credentials** → **Create access key** → CLI use.
4. Local machine:

```bash
aws configure
# AWS Access Key ID: ...
# AWS Secret Access Key: ...
# Default region: us-east-1  (or your choice)
# Default output: json
```

Verify:

```bash
aws sts get-caller-identity
```

### 3. Install Pulumi

```bash
brew install pulumi   # macOS
# or: curl -fsSL https://get.pulumi.com | sh
pulumi login        # app.pulumi.com (free state) OR configure S3 backend
```

### 4. Domain (Route53)

**Option A — domain already in Route53**

- Note the **Hosted zone ID** for Pulumi: `monolith:hostedZoneId`.

**Option B — domain elsewhere (Namecheap, Cloudflare, etc.)**

- After first `pulumi up`, create a **CNAME** from `yourdomain.com` → ALB DNS name (`pulumi stack output albDnsName`).
- Or transfer DNS to Route53 for automatic ACM validation.

### 5. Configure Pulumi stack

```bash
cd infra
pnpm install
pulumi stack init prod
pulumi config set aws:region us-east-1
pulumi config set monolith:domain tools.yourdomain.com
pulumi config set monolith:dbName monolith --secret
pulumi config set monolith:dbUsername monolith --secret
pulumi config set monolith:dbPassword "$(openssl rand -base64 24)" --secret
```

### 6. First deploy (infrastructure only)

```bash
cd infra
pulumi up
```

Note outputs:

- `dbEndpoint` — RDS hostname for `DB_HOST`
- `ecrRepositoryUrl` — where Docker images go
- `albDnsName` — point DNS here if not using Route53 in stack

### 7. Build & push Docker image

```bash
# From repo root, after Dockerfile exists
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin <account-id>.dkr.ecr.us-east-1.amazonaws.com

docker build -t monolith .
docker tag monolith:latest <ecrRepositoryUrl>:latest
docker push <ecrRepositoryUrl>:latest
```

### 8. Production secrets

Store in AWS Secrets Manager (or ECS task env from Pulumi):

- `AUTH0_DOMAIN`, `AUTH0_CLIENT_ID`, `AUTH0_CLIENT_SECRET`, `AUTH0_COOKIE_SECRET`
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `APP_URL=https://yourdomain.com`
- `APP_ENV=production`, `APP_DEBUG=false`

### 9. Run migrations on RDS

From a machine that can reach RDS (bastion, VPN, or one-off ECS task):

```bash
mysql -h <dbEndpoint> -u monolith -p monolith < database/migrations/001_initial.sql
mysql -h <dbEndpoint> -u monolith -p monolith < database/migrations/002_role_permissions.sql
```

### 10. Go-live checklist

- [ ] Auth0 production callbacks match `APP_URL`
- [ ] RDS not publicly accessible; only ECS security group on 3306
- [ ] HTTPS on ALB (add ACM cert + 443 listener when ready)
- [ ] `APP_DEBUG=false`
- [ ] First login → confirm `owner` role in DB
- [ ] Hit `/health` → `database: connected`

---

## What to send back when accounts are ready

Fill `.env` from the template:

```bash
cp .env.example .env
```

Minimum for local login:

```
APP_URL=http://localhost:8000
AUTH0_DOMAIN=...
AUTH0_CLIENT_ID=...
AUTH0_CLIENT_SECRET=...
AUTH0_COOKIE_SECRET=...
BOOTSTRAP_ADMIN_EMAIL=you@example.com
```

Then: `docker compose up -d && php -S localhost:8000 -t public` → visit `/login`.
