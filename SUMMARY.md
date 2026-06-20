## Goal
Deliver a production-ready auth system with RBAC, admin CRUD UI, and verified Laravel alignment.

## Constraints & Preferences
- Rename existing `user` role → `crm_user` with all module permissions
- All modules (Properties, Booking, ECommerce, Posts) are for all logged-in users, not just admin
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

## Key Decisions
- `crm_user` gets ALL module permissions + standard profile/notification permissions; does NOT get admin permissions
- `permission:` middleware added to all module route groups
- Module onUserDelete() individual deletes are redundant with FK CASCADE but kept for model event firing
- ECommerce orders are orphaned (SET NULL) rather than deleted
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
