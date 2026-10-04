# Testing & Browser E2E (`tests/`)

> **How this file loads**: not auto-loaded (traversal stops at the repo-root
> `AGENTS.md`). Referenced from the root `AGENTS.md` — **read it when the task
> involves running, writing, or debugging tests, or driving a browser.**

Two suites, run independently:

- **PHPUnit** — `tests/`, `php artisan test`. Mirror risk is low.
- **Vitest** — `resources/js/__tests__/` + `packages/*/resources/js/__tests__/`,
  `npm run test`. All 363 tests must pass.

## Playwright MCP

Playwright MCP is configured in `~/.config/opencode/opencode.jsonc` (NOT a separate `playwright-mcp.json`).

- **User data dir**: `C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\profile-crm-dashboard` — persistent browser profile (cookies, cache, localStorage survive restarts via `--user-data-dir` + `--save-session`)
- **Output dir**: `C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\output` — screenshots, logs, session state files
- **Session persistence**: `--save-session` saves cookies + localStorage on MCP server shutdown (opencode exit or reconnect). The `--user-data-dir` also persists browser profile data across sessions. Sessions survive opencode restarts automatically — **do not delete the output dir unless you want a fresh session**.
- **Dark mode**: Set via `browser_evaluate` (`localStorage.setItem("theme", "dark")`) before navigating. The `colorScheme: "dark"` context option is unreliable on Windows persistent contexts. Theme persists across sessions automatically via localStorage key `"theme"` (saved by `--save-session`).

### Session-Cleanup (only when explicitly requested)

When you need a completely fresh browser (new login, reset state), run:

```powershell
Remove-Item -LiteralPath "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright" -Recurse -Force -ErrorAction SilentlyContinue; $null = New-Item -ItemType Directory -Path "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\profile-crm-dashboard" -Force; $null = New-Item -ItemType Directory -Path "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\output" -Force
```

**Do NOT run this for normal use** — it destroys all saved sessions and you'll have to re-login.

### Rules

- Never save screenshots or logs to the project root — always use `outputDir`
- `.gitignore` has `/*.png` as safety net, but output should never land in root
- `--no-sandbox`: Not used in our config. Chromium on Windows works fine with sandbox. If you see this message, it's from a previous config or environment variable (`PLAYWRIGHT_MCP_NO_SANDBOX`).

## Testing

### Structure

```
tests/
  Concerns/
    CreatesTestUsers.php          # Shared trait: admin(), editor(), contributor(), regularUser(), createUserWithRole(), seedRolesAndPermissions()
  Feature/
    Ai/
      AiProviderCrudTest.php      # Provider CRUD + validation (6 tests)
      AiProviderTestConnectionTest.php  # Connection test validation (4 tests)
    Admin/
      UserManagementTest.php      # Admin user CRUD (10 tests)
      UserCreationTest.php        # Default role assignment, audit logging (13 tests)
      RoleCreationTest.php        # Role CRUD + admin role protection (6 tests)
      ImpersonationTest.php       # Impersonation flows + edge cases (7 tests)
      BulkOperationsTest.php      # Bulk user actions (6 tests)
    Auth/
      AuthenticationTest.php, RegistrationTest.php, RateLimitingTest.php, PasswordUpdateTest.php, PasswordResetTest.php, PasswordConfirmationTest.php, EmailVerificationTest.php
    Blog/
      BlogCrudTest.php            # Post CRUD lifecycle (7 tests)
      BlogPermissionsTest.php     # Role-based access (6 tests)
      BlogCategoryTest.php        # Category CRUD (5 tests)
      BlogTagTest.php             # Tag CRUD (5 tests)
      BlogPublicTest.php          # Public blog routes (5 tests)
      BlogSeoTest.php, BlogBulkActionTest.php
    Console/
      CreateAdminCommandTest.php  # Admin bootstrap incl. email_verified_at (5 tests)
      CleanupOrphanMediaCommandTest.php  # Orphan media cleanup (3 tests)
      ListPermissionsCommandTest.php     # Permission listing + --role filter (5 tests)
      NotificationPurgeCommandTest.php   # Notification purge (2 tests)
      PruneAuditLogsCommandTest.php      # Audit pruning incl. --days (3 tests)
      PublishScheduledContentCommandTest.php  # Scheduled publishing (3 tests)
    Deploy/
      DeployEndpointTest.php      # Token auth, verification, upload, cache-clear pipeline (11 tests)
    Jobs/
      SendEmailTest.php           # Mailable dispatch + retry (3 tests)
      GenerateSeoForPostTest.php  # SEO generation incl. missing provider (4 tests)
    Maintenance/
      MaintenanceModeTest.php     # 503 Blade + bypass flows (19 tests)
    Media/
      MediaUploadTest.php         # Upload/destroy, traversal safety (9 tests)
    Notifications/
      NotificationPollTest.php    # Poll endpoints, auth, filters (8 tests)
      NotificationServiceTest.php # Cached count, purge (3 tests)
    Performance/
      DatabaseIndexTest.php       # Index presence assertions (6 tests)
      DatabasePerformanceTest.php # Cache TTL + N+1 guard (6 tests)
    Public/
      PublicPagesTest.php         # Welcome, about, services, contact (4 tests)
    Security/
      XssSanitizationTest.php     # HTML sanitizer allowlists (7 tests)
      MediaPathTraversalTest.php  # Path traversal guards (4 tests)
      ContentSecurityPolicyTest.php      # CSP headers (4 tests)
      ConfigureSessionLifetimeTest.php   # DB-driven session timeout (3 tests)
      MaintenanceBypassTest.php, IpAccessControlTest.php, PasswordComplexityTest.php
    DataIsolationTest.php         # HTTP-level data isolation checks
    FeatureRegistryTest.php       # Singleton, nav items, permissions (5 tests)
```

### Factories

| Model          | File                                                          | States                                             |
| -------------- | ------------------------------------------------------------- | -------------------------------------------------- |
| `User`         | `packages/platform/src/Database/Factories/UserFactory.php`      | `admin()`, default: unverified                     |
| `Post`         | `packages/blog/src/Database/Factories/PostFactory.php`         | `published()`, `draft()`, `archived()`, `future()` |
| `PostCategory` | `packages/blog/src/Database/Factories/PostCategoryFactory.php` | —                                                  |
| `PostTag`      | `packages/blog/src/Database/Factories/PostTagFactory.php`      | —                                                  |

> Factories are **package-owned**, at `src/Database/Factories/` — the package mirrors the
> upstream source layout, not a flattened `database/factories/`. This app has no
> `database/factories/` at all, so such a path resolving only inside `apps/laravel.shop`
> is a reference to a sibling mirror, not to anything here.

### Key Patterns

- **Shared trait**: `CreatesTestUsers` provides `admin()`, `regularUser()`, `createUserWithRole()` for consistent user creation across all tests
- **Permissions**: Use `seedRolesAndPermissions()` in `setUp()` to ensure roles exist before testing
- **Testing auth**: `$this->actingAs($user)` with `$user->forceFill(['email_verified_at' => now])->save()` for verified users
- **Testing permissions**: Create role with `Permission::create()`, assign via `$user->assignRole()`, then assert 403 for unauthorized
- **AI tests**: Validation-only (no API mocking — tests auth, required fields, invalid providers)
- **Deploy tests**: Token via POST body (`['token' => $this->token]`), unauthenticated returns 403 (not 401)
- **Notification tests**: Unauthenticated requests return 401 JSON (not redirect) due to JSON exception handler
- **Performance**: `$this->captureQueryCount(callable)` / `$this->assertQueryCount(int, callable)` helpers on `tests/TestCase.php` for cache-hit and N+1 assertions; `Cache::flush()` in `setUp()` to avoid cross-test pollution

### Running Tests

```bash
php artisan test --compact                           # All tests (compact output)
php artisan test --parallel --compact                # All tests, parallel via paratest
php artisan test --compact tests/Feature/Blog/       # All blog tests
php artisan test --compact --filter=testName         # Specific test
npm run test                                         # Vitest frontend tests
npm run check:ci                                     # Local superset (see note above)
pnpm test:e2e                                        # Playwright e2e against APP_URL (defaults to 127.0.0.1:8000)
```

### Browser E2E

The workspace ships one Playwright harness at the repo root
(`playwright.config.ts` + `e2e/`) usable against any served app — point
`APP_URL` at the target site (e.g. `http://shop-laravel.local`,
`http://laravel.local`, or a booking site) and run `pnpm test:e2e`.
Login uses the demo admin (`nstudio.webdesign@gmail.com` / `azerty123/*`,
override with `E2E_EMAIL`/`E2E_PASSWORD`). The shop admin smoke
(`e2e/shop-admin-resources.spec.ts`) verifies the kernel resource pages
render shop descriptors (customers/orders columns + the coupon product
combobox) and needs seeded demo data. Install the browser once:
`npm run test:e2e:install`. Agents authoring throwaway browser checks can
also use the global `webapp-testing` skill (Python Playwright + output under
`%TEMP%\opencode\playwright`).

- `main` — production-ready
- `beta` — active development
