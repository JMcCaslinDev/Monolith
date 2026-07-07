# AWS hosting guide

Step-by-step to get Monolith live on AWS with Pulumi. One domain, one bill.

## Prerequisites

- AWS account with admin access (or scoped IAM user for Pulumi)
- Domain in Route53 (or DNS you can point to AWS)
- Auth0 tenant (production application)
- Local: Node 20+, pnpm, Pulumi CLI, AWS CLI configured (`aws configure`)

## 1. AWS account bootstrap

### IAM user for Pulumi (recommended)

1. IAM → Users → Create user `pulumi-deploy`.
2. Attach policy `AdministratorAccess` (tighten later) or a custom policy for ECS, RDS, EC2, ELB, Route53, ACM, Secrets Manager, IAM pass-role.
3. Create access key → save for `aws configure` or Pulumi CI.

### Enable required services (first-time)

No console toggle needed — Pulumi creates resources on first `pulumi up`. Ensure your account is out of free-tier-only restrictions for RDS if applicable.

## 2. Auth0 production app

1. Auth0 Dashboard → Applications → Create → Regular Web Application.
2. Settings:
   - **Allowed Callback URLs:** `https://yourdomain.com/auth/callback`
   - **Allowed Logout URLs:** `https://yourdomain.com`
   - **Allowed Web Origins:** `https://yourdomain.com`
3. Copy Domain, Client ID, Client Secret → store in AWS Secrets Manager (Pulumi creates the secret).

Add localhost URLs for dev in a separate Auth0 application or the same app with multiple callbacks.

## 3. Configure Pulumi stack

```bash
cd infra
pnpm install
pulumi login                    # pulumi.com or s3://your-state-bucket
pulumi stack init prod
pulumi config set aws:region us-east-1
pulumi config set monolith:domain yourdomain.com
pulumi config set monolith:dbName monolith --secret
pulumi config set monolith:dbUsername monolith --secret
pulumi config set monolith:dbPassword "$(openssl rand -base64 24)" --secret
```

Copy `Pulumi.prod.yaml.example` to guide additional keys. Auth0 secrets are injected via Secrets Manager in the stack.

## 4. What Pulumi provisions

| Resource | Purpose |
|----------|---------|
| VPC + public/private subnets | Network isolation |
| RDS MariaDB 11 | Application database |
| ECS Fargate cluster + service | PHP app containers |
| ALB + target group | HTTPS load balancing |
| ACM certificate | TLS for your domain |
| Route53 A/AAAA alias | `yourdomain.com` → ALB |
| ECR repository | Docker images |
| Secrets Manager | DB creds, Auth0 secrets |
| Security groups | ALB → ECS → RDS only |

Run `pulumi up` from `infra/` after the stack is configured and a Docker image is pushed (see README deploy section).

## 5. Build and push container image

```bash
# From repo root (Dockerfile to be added when app is containerized)
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin <account>.dkr.ecr.us-east-1.amazonaws.com
docker build -t monolith .
docker tag monolith:latest <account>.dkr.ecr.us-east-1.amazonaws.com/monolith:latest
docker push <account>.dkr.ecr.us-east-1.amazonaws.com/monolith:latest
```

Set the image tag in Pulumi config or let CI update the ECS task definition on each deploy.

## 6. DNS

If the domain is in Route53, Pulumi creates the hosted zone record or uses an existing zone id via config `monolith:hostedZoneId`.

If DNS is elsewhere, create a CNAME from `yourdomain.com` to the ALB DNS name output by `pulumi stack output albDnsName`.

## 7. Go-live checklist

- [ ] RDS MariaDB reachable only from ECS security group
- [ ] ALB listener 443 with valid ACM cert
- [ ] HTTP → HTTPS redirect on ALB
- [ ] Auth0 callback URLs match production domain
- [ ] `.env` / Secrets Manager: `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Run migrations against RDS (`database/migrations/*.sql`)
- [ ] Bootstrap first user as `owner` (see README)
- [ ] Smoke test: login, dashboard, permission deny returns 403 + event

## 8. Ongoing deploys

```bash
pnpm build                    # production assets
docker build && docker push   # or GitHub Actions
cd infra && pulumi up
```

## Cost notes (rough)

- RDS `db.t4g.micro` + single Fargate task: often ~$30–80/mo depending on region
- ALB has a fixed hourly cost
- Use one NAT gateway or VPC endpoints consciously — NAT adds cost

## Troubleshooting

| Symptom | Check |
|---------|-------|
| 502 from ALB | ECS task health, container logs in CloudWatch |
| DB connection refused | Security group, RDS in private subnet, correct `DB_HOST` |
| Auth0 redirect mismatch | Callback URL exact match including https |
| Assets 404 | Run `pnpm build`; ensure `public/build` copied into image |
