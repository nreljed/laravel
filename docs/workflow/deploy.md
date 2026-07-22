# Deployment

## CI/CD Pipeline

Deploys automatically on push to `main`.

### GitHub-Hosted Runner (`deploy.yml`)

1. Checkout + tags → Setup PHP 8.4 + Node 22
2. git-cliff: parse commits → bump semver → CHANGELOG.md
3. Build .env (SQLite) → composer install → migrate
4. npm install → typecheck → build → test → pint
5. composer install --no-dev → Package `deploy.zip`
6. Create GitHub Release with tag `vX.Y.Z`
7. Curl `/deploy/check?token=` on server to trigger deploy

### Server (`DeployController::check()`)

1. Verify token + lock file (10 min TTL)
2. Fetch latest release from GitHub API
3. Download `deploy.zip`, extract, restore `.env`
4. Run: migrate, create-admin, cache, storage:link

## Endpoints

| Endpoint                         | Purpose                 |
| -------------------------------- | ----------------------- |
| `GET /deploy/check?token=`       | Main deploy entry point |
| `GET /deploy/cache-clear?token=` | Emergency reset         |

## GitHub Secrets

| Secret                                          | Purpose                                 |
| ----------------------------------------------- | --------------------------------------- |
| `DEPLOY_TOKEN`                                  | Must match `config('app.deploy_token')` |
| `GH_TOKEN`                                      | Fine-grained PAT (Contents: Read+Write) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`   | Production MySQL                        |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME` | Initial admin bootstrap                 |

## After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Then run typecheck + build + phpunit and deliver results.
