# Project Setup After Copy

When this project was copied from another Laravel app (e.g., `shop-laravel` → `booking-laravel`), several identity-bearing files must be changed to avoid confusion between the two projects. Run through this checklist every time you copy a project.

## 1. APP_KEY — Regenerate (Critical)

The `.env` `APP_KEY` is used to encrypt sessions, cookies, and other sensitive data. If two projects share the same key, sessions from one can potentially be decrypted by the other.

```bash
php artisan key:generate
```

Also check that `.env.production` has its own unique key (it should already, but verify).

## 2. `.env` — App Identity

| Field | What to change |
|-------|---------------|
| `APP_NAME` | Must be unique per project |
| `APP_URL` | Must match the local dev domain |
| `DB_DATABASE` | Must be a unique database |
| `DB_USERNAME` / `DB_PASSWORD` | Unique credentials |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Unique per app |
| `SESSION_DOMAIN` | Must not overlap with other projects |

## 3. `.env.example` & `.env.production.example`

These template files often retain the **source project's** identity. Always update:

- `APP_NAME` — the copied template will still say "Shop App" etc.
- `APP_URL` — production URL must be the new project's domain
- Other defaults (DB, mail, etc.)

## 4. Git Remote

Verify the git origin points to the correct repository:

```bash
git remote -v
```

The remote must match **this** project, not the one it was copied from.

## 5. Database

Ensure the project connects to its own database, not the source project's database. Check both `.env` (local) and `.env.production` (production).

## 6. opencode Desktop Project Registry

The opencode Desktop stores project paths and session data in:

```
%APPDATA%\ai.opencode.desktop\opencode.global.dat
```

After copying a project, the Desktop can confuse the two projects — the **source project's session** may point to the **new copy's directory**, or both may share the same session ID.

**Fix:** Close opencode Desktop, delete `opencode.global.dat`, reopen, and re-add both projects manually.

## 7. Source-Specific Config Files

Check if the copy inherited config files that belong only to the source project. For example, `config/shop.php` should not exist in a booking-only app. Delete any such files.

## 8. Hardcoded References

Search the codebase for the source project's name, URL, and domain:

```bash
rg -i "Shop App" app/ resources/ config/ routes/
rg -i "shop\.laravel" app/ resources/ config/ routes/
```

Replace any matches that are not part of a shared library.

## 9. Stripe / Third-Party API Keys

Even placeholder keys should be unique per project to avoid accidental cross-environment usage. Regenerate if the source project's keys are not placeholders.

## 10. Deployment Configuration

- `.env.production` — verify `APP_KEY`, `APP_URL`, `DB_*`, `MAIL_*`, `DEPLOY_TOKEN`, `GITHUB_TOKEN`
- `DEPLOY.md` — update domain, server paths, database names
- GitHub Actions — verify secrets are set for **this** project's repo (use `gh secret list`)
