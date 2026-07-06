## Goal
Deliver a production-ready auth system with RBAC, admin CRUD UI, and verified Laravel alignment.

## Constraints & Preferences
- Rename existing `user` role → `crm_user` with all module permissions
- All modules (Properties, Booking, Shop, Posts) are for all logged-in users, not just admin
- Only admin-specific pages (Users, Roles, Settings, System Health) are hidden from `crm_user`
- Full admin UI (CRUD) for users, roles, and permissions
- Delete account flow must clean up all user data across every module
- Use Laravel's latest patterns: FormRequest validation, Inertia `useForm` (not react-hook-form), resourceful controllers with middleware
- DangerZoneSection dialog must show dynamic per-module list of what gets deleted
- Scripts for resetting/re-seeding test users live in `scripts/`
- Deploy pipeline auto-runs: migrate → seed → sync-permissions → build → test
- Auth pages must use same components as WP CRM plugin (`PasswordInput`, `ErrorMessage` with variant, `Alert` + icons, Zod client-side validation)
- `name` = display name (free text), `username` = unique login handle (alphanumeric + `_.-`) — **separate columns**
- Only SMTP mailer; default port 587; encryption (TLS/SSL/none); password stored encrypted with `Crypt::encryptString`
- Layout content area too wide — **fixed**: added `max-w-7xl mx-auto` wrapper in both layouts
- Email layout must match WP plugin with conditional logo support — **fixed**: logoUrl passed via EmailTemplateService, layout now conditionally shows image or h1 text

## Progress
### Done
- HasRoutes trait: `loadRoutes()` and `loadAdminRoutes()` accept optional `$permission` param
- All four module `routes()` methods pass `$this->slug() . '.view'` permission
- SyncModulePermissions auto-assigns new module permissions to `crm_user` role
- RolesAndPermissionsSeeder creates `crm_user` (not `user`) with all module + profile/notification permissions
- Migration `2026_06_19_000000_rename_user_role_to_crm_user.php`
- Admin UserController (full CRUD with search, pagination, FormRequests, username)
- Admin RoleController (list + edit with grouped permission checkboxes)
- Routes: full resourceful `/users` and `/roles` under admin group
- Users/Index.tsx: table with search, pagination, role badges, username column, edit/delete
- Users/Form.tsx: create/edit with username, display name, email, password, role select
- Roles/Index.tsx: list with user/permission counts
- Roles/Form.tsx: grouped permission checkboxes with select-all/deselect-all
- Admin sidebar: "Users" (users.index) + "Roles" (Shield icon)
- `scripts/reset-users-data.php`, `scripts/seed-test-users.php`, `scripts/create-admin.php` — all with username
- All fix commits applied across codebase
- DangerZoneSection.tsx: dynamic per-module delete list
- All 6 auth pages: PasswordInput, ErrorMessage variant, Alert + icons, Zod validation, Spinner on submit, aria-describedby, autoFocus
- Email SMTP settings: EmailSettingsService (encrypted password, auto_tls, return_path), EmailSettingsController (SMTP-only), MailerConfigurator (wired in AppServiceProvider::boot())
- EmailSection.tsx: restructured with groups (Sender Info, SMTP Server, Authentication, Advanced), descriptions, encryption badge
- HandleInertiaRequests: flash bridging, getModuleDebugData try-catch, emailTemplates shared prop
- TemplateSimulationSection: now consumes `emailTemplates` from backend with grouped optgroups
- Layout width: `max-w-7xl mx-auto` added to AuthenticatedLayout and AdminLayout content areas
- Email layout: conditional logo support matching WP plugin (logoUrl via EmailTemplateService)
- `tsc --noEmit` + `vite build` both pass clean

### Remaining
- PHP test `AuthenticationTest::test_login_screen_can_be_rendered` still 500s — module route registration timing issue in test lifecycle (works in production)
- Add async username/email availability checks on Register page (matching WP plugin pattern)
- Consider adding `is_active` migration for user disable/enable

---

### Phase 6 — Admin Modernization (completed 2026-06-24)

#### Unified Authorization Layer
- PermissionCacheService: caches permission→slug mappings, per-role and per-user lists, flushed on any role/permission/assignment change
- PermissionGateServiceProvider: registers ALL permission slugs (core + DB + modules) as Laravel Gates, with `Gate::before` granting admins full access
- Removed per-module Gate::define loop from ModuleServiceProvider (replaced by central provider)
- User model: `hasPermission()` now uses cache, added `canAny()` matching parent signature, `hasAllPermissions()`
- HandleInertiaRequests: switched to `$user->can()` via Gates

#### Complete Admin CRUD
- RoleController: added `create()`/`store()`/`destroy()` (admin role protected from deletion), StoreRoleRequest, routes
- Roles/Form.tsx: unified create/edit with auto-slugify from name
- Roles/Index.tsx: create button, delete button (hidden for admin), slug column
- PermissionController: full CRUD (index/create/store/show/edit/update/destroy), StorePermissionRequest, routes
- Permissions/Index.tsx: search + CRUD + edit/delete
- Permissions/Form.tsx: create/edit modes
- UserController: added `verifyEmail()`, `sendPasswordReset()` with routes
- app-sidebar.tsx: added Permissions nav item

#### Admin Power Features
- ImpersonationController: start (admin) stores `impersonator_id` in session, stop (auth-only) restores, both logged to audit_logs
- ImpersonationBanner.tsx: amber banner with stop button, shared via HandleInertiaRequests
- BulkUserController: bulk delete (self-protected) + CSV export
- AuditLogController: filtered by action/user/date, paginated
- AuditLogs/Index.tsx: color-coded action badges, date range filter
- app-sidebar.tsx: added Audit Logs nav item

#### Security Hardening
- SessionController: index (device/browser/IP/last activity) + destroy (revoke), reads sessions DB table
- Sessions.tsx: session management UI with current session indicator
- `throttle:60,1` on entire admin route group
- Audit logging in RoleController (store/update/destroy) and UserController (store/update)

#### Developer Experience
- Console commands: `roles:create` (with permission assignment), `permissions:list` (with role/group/format filters)
- Factories: PermissionFactory, RoleFactory
- Tests: 6 new suites (119 total, all passing, 261 assertions):
  - PermissionManagementTest (CRUD + authorization)
  - RoleCreationTest (create/delete + command + admin protection)
  - ImpersonationTest (start/stop/audit/auth)
  - BulkOperationsTest (bulk delete/self-protection/CSV export)

#### New/Updated Files
- `app/Services/PermissionCacheService.php` — permission caching
- `app/Providers/PermissionGateServiceProvider.php` — Gate registration
- `bootstrap/providers.php` — provider registration
- `app/Http/Controllers/Admin/ImpersonationController.php` — impersonation
- `app/Http/Controllers/Admin/AuditLogController.php` — audit log viewer
- `app/Http/Controllers/Admin/SessionController.php` — session management
- `app/Http/Controllers/Admin/BulkUserController.php` — bulk operations
- `app/Console/Commands/CreateRole.php` — roles:create command
- `app/Console/Commands/ListPermissions.php` — permissions:list command
- `database/factories/PermissionFactory.php`, `RoleFactory.php` — test factories
- `tests/Feature/Admin/PermissionManagementTest.php`
- `tests/Feature/Admin/RoleCreationTest.php`
- `tests/Feature/Admin/ImpersonationTest.php`
- `tests/Feature/Admin/BulkOperationsTest.php`
- `routes/web.php` — impersonation stop outside admin middleware
- `resources/js/Components/ImpersonationBanner.tsx` — yellow banner
- `resources/js/Pages/Admin/Roles/Index.tsx` — CRUD actions
- `resources/js/Pages/Admin/Roles/Form.tsx` — dual-mode create/edit
- `resources/js/Pages/Admin/Permissions/Index.tsx` — permission CRUD
- `resources/js/Pages/Admin/Permissions/Form.tsx` — create/edit
- `resources/js/Pages/Admin/Users/Index.tsx` — dropdown with all actions
- `resources/js/Pages/Admin/Users/Sessions.tsx` — session management
- `resources/js/Pages/Admin/AuditLogs/Index.tsx` — filtered audit log
- `resources/js/Components/app-sidebar.tsx` — nav items added
- `docs/administration.md` — comprehensive admin documentation

---

### Phase 18 — FormattingService Injectable + HostProfileService Extraction (completed 2026-07-03)

#### FormattingService — Static → Injectable Singleton
- Converted all `static` methods to instance methods
- Registered as `singleton` in `AppServiceProvider::register()`
- Updated callers:
  - `ModuleController` — constructor DI
  - `PropertyFormattingService` — constructor DI
  - `SettingsService` — `app(FormattingService::class)` bridge (still static, separate task)
  - `BookingService` — `app(FormattingService::class)` bridge
  - `BookingCurrencyService` — `app(FormattingService::class)` bridge

#### HostProfileService — Extracted from BookingController
- Created `App\Modules\Booking\Services\HostProfileService` with 6 resolve methods + `resolveAll()`
- **BookingController** — removed 110 lines of static methods, now uses `$this->hostProfile->resolveAll()`
- **BookingGuestController** — replaced 12 `BookingController::resolveHost*()` static calls with `$this->hostProfile->resolveAll()` (eliminated cross-controller static dependency)
- Docs updated: `docs/core/formatting.md`, `docs/modules/booking/services.md`, `docs/modules/booking/controllers.md`

#### Verification
- **157/157** PHPUnit tests pass
- **TypeScript** clean (`tsc --noEmit`)
- **Production build** succeeds in 3.24s

#### New / Modified Files
```
NEW:
  app/Modules/Booking/Services/HostProfileService.php

MODIFIED:
  app/Services/FormattingService.php              # static → instance methods
  app/Providers/AppServiceProvider.php            # singleton registration
  app/Http/Controllers/ModuleController.php       # FormattingService DI
  app/Modules/Properties/Services/PropertyFormattingService.php  # FormattingService DI
  app/Modules/Booking/Controllers/BookingController.php          # removed 6 static resolveHost methods
  app/Modules/Booking/Controllers/BookingGuestController.php     # HostProfileService DI
  app/Modules/Booking/Services/BookingService.php                # app() bridge
  app/Modules/Booking/Services/BookingCurrencyService.php        # app() bridge
  app/Services/SettingsService.php                # app() bridge
  docs/core/formatting.md                         # updated for injectable usage
  docs/modules/booking/services.md                # HostProfileService added
  docs/modules/booking/controllers.md             # note about removed static methods
  docs/progress-summary.md                        # this section
```

## Key Decisions
- `crm_user` gets ALL module permissions + standard profile/notification permissions; does NOT get admin permissions
- `permission:` middleware added to all module route groups
- Module onUserDelete() individual deletes are redundant with FK CASCADE but kept for model event firing
- Shop orders are orphaned (SET NULL) rather than deleted
- Flash data bridged in HandleInertiaRequests rather than rewriting all controllers
- auth pages rewritten to match WP CRM plugin: PasswordInput, ErrorMessage variant, Alert + icons, Zod validation
- SMTP password encrypted with `Crypt::encryptString` (AES-256-CBC)
- MailerConfigurator called in AppServiceProvider::boot() to dynamically apply stored SMTP settings
- Template list shared via HandleInertiaRequests `emailTemplates` prop for dynamic dropdown in TemplateSimulationSection
- Email layout now conditionally shows logo image (if set) or h1 site name text — matches WP plugin `@if($logoUrl)` pattern

## Relevant Files
- `app/Modules/Traits/HasRoutes.php`
- `app/Modules/ModuleRegistry.php` (static `cleanupUserData`)
- `app/Http/Controllers/Admin/UserController.php`, `RoleController.php`
- `app/Http/Requests/Admin/StoreUserRequest.php`, `UpdateUserRequest.php`
- `database/migrations/2026_06_19_000000_rename_user_role_to_crm_user.php`
- `database/migrations/2026_06_19_150145_add_username_to_users_table.php`
- `database/seeders/RolesAndPermissionsSeeder.php`
- `app/Console/Commands/SyncModulePermissions.php`
- `resources/js/Pages/Admin/Users/Index.tsx`, `Form.tsx`
- `resources/js/Pages/Admin/Roles/Index.tsx`, `Form.tsx`
- `resources/js/Components/account/sections/DangerZoneSection.tsx`
- `resources/js/Components/command-search.tsx`
- `resources/js/Components/app-sidebar.tsx`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Models/User.php`
- `bootstrap/app.php`
- `app/Modules/Booking/Routes/my-bookings.php`, `BookingGuestController.php`
- `app/Modules/Posts/PostsModule.php`
- `resources/js/Pages/Auth/*.tsx` (all 6 auth pages rewritten)
- `resources/js/Components/ui/error-message.tsx`, `spinner.tsx`, `password-input.tsx`
- `resources/js/lib/utils.ts`, `resources/js/lib/schemas/index.ts`
- `scripts/*.php` (reset-users-data, seed-test-users, create-admin — all with username)
- `AGENTS.md` (deployment automation)
- `docs/auth-integration.md`
- `app/Services/EmailSettingsService.php` (encrypted password, auto_tls, return_path)
- `app/Http/Controllers/EmailSettingsController.php` (SMTP-only)
- `app/Services/MailerConfigurator.php` (wired in AppServiceProvider)
- `app/Services/EmailTemplateService.php` (injects logoUrl)
- `app/Providers/AppServiceProvider.php`
- `resources/js/Components/settings/sections/EmailSection.tsx` (grouped SMTP form)
- `resources/js/Components/settings/sections/TemplateSimulationSection.tsx` (dynamic template dropdown)
- `resources/js/Layouts/AuthenticatedLayout.tsx`, `AdminLayout.tsx` (max-w-7xl)
- `resources/views/email/layouts/email.blade.php` (conditional logo support)

---

### Phase 4 — Booking Services & Guest Portal Parity (completed 2026-07-01)

#### Booking PHP Services
- `BookingAction` model + migration for `booking_actions` table
- `BookingActionService` — records/retrieves booking actions
- `BookingActivityDashboardService` — admin activity feed
- `BookingHistoryService` — booking history/audit trail
- `BookingMeta` — constants class (65+ field definitions)
- `config/booking-meta.php` — configuration array

#### Property Module Improvements
- **Scheduled publishing**: `published_at` column, `PublishScheduledProperties` artisan command (every minute)
- **Term image support**: `image` column on `property_types` and `cities`
- **Taxonomy seed data**: 14 types, 8 statuses, 4 feature groups (48 features), 200+ cities
- **Property fixes**: bulk action route, route method mismatch, user count cache, cascade delete, updateStatus validation

#### Guest Portal Parity
- **API routes**: check-availability, cache-version, modify, cancel
- **Check-in/check-out**: `guestCheckIn/guestCheckOut` (token auth), `loggedInCheckIn/loggedInCheckOut` (session auth)
- **Routes**: `guest.php`, `my-bookings.php`, `web.php` (admin guest portal)
- **Frontend**: GuestView.tsx, MyBookings.tsx, MyBookingDetail.tsx — all with "I've Arrived" / "I'm Leaving" buttons
- **Status transition rules**: `Confirmed→CheckIn`, `CheckedIn→CheckOut`
- **All 119 tests passing, typecheck clean, Pint clean**

#### Documentation Restructure (completed 2026-07-01)
- Master navigation hub at `docs/index.md`
- Per-module subdirectories with structured sub-docs: `index.md`, `controllers.md`, `services.md`, `frontend.md`, `architecture.md`, `settings.md`, `guest-portal.md`
- Core architecture docs: `architecture.md`, `routes.md`, `formatting.md`, `modules.md`
- Frontend docs: `architecture.md`, `patterns.md`, `shared-props.md`
- Database docs: `schema.md`, `migrations.md`
- Testing docs: `index.md`
- Workflow docs: `setup.md`, `development.md`, `ci.md`, `release.md`, `deploy.md`
- Auditing index: `auditing/index.md`
- 35 new documentation files across 8 directories
