# Deployment

> Full deployment documentation: [DEPLOY.md](../../DEPLOY.md)

## Quick Reference

### Server Deploy Endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /deploy/check?token=` | Main deploy entry point |
| `GET /deploy/cache-clear?token=` | Emergency reset (migrate:fresh + cache clear) |

### Required GitHub Secrets

| Secret | Purpose |
|--------|---------|
| `DEPLOY_TOKEN` | Must match `config('app.deploy_token')` |
| `GH_TOKEN` | Fine-grained PAT with Contents: Read and Write |
| `DB_DATABASE` | Production database name |
| `DB_USERNAME` | Production username |
| `DB_PASSWORD` | Production password |
| `ADMIN_EMAIL` | Initial admin email |
| `ADMIN_PASSWORD` | Initial admin password |
| `ADMIN_NAME` | Initial admin name |

### Server Process

On deploy trigger:
1. Verify token, check `.deploy-lock` (10 min TTL)
2. Fetch latest release from GitHub API
3. Download `deploy.zip`, extract over project root
4. Restore `.env` from backup (never overwritten)
5. Run: `package:discover`, `migrate`, `app:create-admin`, `config:cache`, `route:cache`, `view:cache`, `storage:link`
