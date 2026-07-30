# Deployment

## CI/CD Pipeline

Deploys automatically on push to `main`. The pipeline has four sequential jobs:

1. **ci** — lint, typecheck, test, build (ubuntu, reusable workflow_call)
2. **package** — build `deploy.zip` on Windows (reusable workflow_call)
3. **release** — create GitHub Release with `deploy.zip` artifact
4. **push-deploy** — upload to server, with Cloudflare-aware fallback

### Cloudflare Handling

The server is behind Cloudflare, which intercepts file upload POSTs with a JS challenge
page (HTTP 200 HTML). The pipeline detects this by checking the first byte of the upload
response: `{` = JSON from the app, `<` = Cloudflare challenge. If Cloudflare blocks the
upload, it falls back to `/deploy/check` (server-side GitHub API download):

```bash
# Upload first
UPLOAD_RESP=$(curl -s -w "\n%{http_code}" --max-time 300 \
  -F "file=@deploy.zip;filename=deploy.zip" \
  "https://laravel.tunispromo.tn/deploy/upload?token=$DEPLOY_TOKEN")
HTTP_CODE=$(echo "$UPLOAD_RESP" | tail -1)
BODY=$(echo "$UPLOAD_RESP" | sed '$d')
if [ "$HTTP_CODE" = "200" ] && echo "$BODY" | head -1 | grep -q '^{'; then
  echo "Deployed via /deploy/upload"
elif CHECK_RESP=$(curl -s -X POST --max-time 300 \
  "https://laravel.tunispromo.tn/deploy/check?token=$DEPLOY_TOKEN") \
  && echo "$CHECK_RESP" | head -1 | grep -q '^{'; then
  echo "Deployed via /deploy/check (GitHub API)"
else
  exit 1
fi
```

### Server Deploy Flow (`DeployController::check()`)

1. Verify token + lock file (10 min TTL)
2. Fetch latest release from GitHub API
3. Download `deploy.zip`, extract, restore `.env`
4. `cleanStaleMigrations()` — removes any migration files absent from the release
5. `php artisan migrate`
6. `php artisan app:create-admin`
7. `php artisan config:cache`, `route:cache`, `view:cache`
8. `php artisan storage:link`

## Endpoints

All deploy endpoints are **POST-only**, token-protected, CSRF-exempt, and rate-limited
(5 requests per 1 minute per IP).

| Endpoint                          | Purpose                                 |
| --------------------------------- | --------------------------------------- |
| `POST /deploy/upload?token=`      | Upload `deploy.zip` directly            |
| `POST /deploy/check?token=`       | Download latest release from GitHub API |
| `POST /deploy/migrate?token=`     | Run pending migrations only             |
| `POST /deploy/cache-clear?token=` | Emergency cache/config/route/view clear |
| `POST /deploy/create-admin`       | Reset admin password (see below)        |

### /deploy/create-admin

Sets or resets the admin user password. Useful when `ADMIN_PASSWORD` env var is not set
or you need to regain access.

```bash
# Token in query string, password in query string
curl -X POST "https://laravel.tunispromo.tn/deploy/create-admin?token=TOKEN&password=newpassword"

# Or token in query string, custom email/name in POST body
curl -X POST "https://laravel.tunispromo.tn/deploy/create-admin?token=TOKEN&password=newpassword&email=admin@example.com&name=Admin"
```

**Note:** The current password becomes `password` immediately — no current password check
is required, so use with caution.

## GitHub Secrets

| Secret                                          | Purpose                                 |
| ----------------------------------------------- | --------------------------------------- |
| `DEPLOY_TOKEN`                                  | Must match `config('app.deploy_token')` |
| `GH_TOKEN`                                      | Fine-grained PAT (Contents: Read+Write) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`   | Production MySQL                        |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME` | Initial admin bootstrap                 |

## Rate Limiting

All deploy routes use `throttle:5,1` — 5 requests per 1 minute per IP. If you hit
the limit, wait 60 seconds and retry. The old `throttle:5,60` (5 per 60 **minutes**)
was fixed in v0.1.30.

## Known Issues

- **Cloudflare file upload blocking:** The pipeline handles this via the JSON-first-byte
  check and falls back to `/deploy/check`. This is reliable but adds ~30s to deploy time.
- **`ADMIN_PASSWORD` not in build .env:** The `deploy-package.yml` generates `.env` without
  `ADMIN_PASSWORD`. The admin bootstrap during deploy always returns "Password is required."
  Use `/deploy/create-admin` after deploy to set a password.

## After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Then run typecheck + build + phpunit and deliver results.
