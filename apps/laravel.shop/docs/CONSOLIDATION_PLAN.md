# Core Consolidation Plan

Consolidate the Shop and Posts modules into first-class Laravel core, remove the Booking and Properties modules entirely, and delete the module infrastructure that was built to support them.

---

## 1. Goal

- **Shop** → `app/Shop/` (namespace `App\Shop`)
- **Posts** → `app/Blog/` (namespace `App\Blog`)
- **Booking** → deleted
- **Properties** → deleted
- **Module infrastructure** → deleted (`ModuleRegistry`, `ModuleLoader`, `ModuleInterface`, `HasRoutes`, `BookableRegistry`, `ModuleController`, `Module` model)

All routes, controllers, models, services, frontend pages, policies, permissions, and tests should live under the normal Laravel `app/` tree and be wired up via conventional route/service-provider files.

---

## 2. Constraints & Preferences

- Do **not** leave a parallel module system alongside the new core layout.
- Keep route names stable where possible (`posts.*` stays `posts.*`).
- Keep the existing Inertia/React frontend structure intact; only move page entry files and update Vite aliases.
- Run `php artisan test`, `vendor/bin/pint`, `npm run typecheck`, and `npm run build` after each major batch of changes.
- Do **not** commit secrets or `.env` changes.

---

## 3. Progress

### Done
- Created `app/Shop/` and `app/Blog/` trees.
- Moved every Shop and Blog model/controller/service/provider into the new namespaces.
- Deleted `app/Modules/Booking/`, `app/Modules/Properties/`, `app/Modules/Contracts/`, `app/Modules/Traits/`, `app/Modules/ModuleRegistry.php`, `app/Modules/ModuleLoader.php`, `app/Modules/BookableRegistry.php`.
- Renamed `App\Modules\Shop` → `App\Shop` and `App\Modules\Posts` → `App\Blog` across all PHP files.
- Created `ShopServiceProvider` and `BlogServiceProvider`.
- Registered both providers in `bootstrap/providers.php`.
- Removed `ModuleEnabled` middleware and `module:` middleware alias from `bootstrap/app.php`.
- Added `routes/api.php` + `api` middleware group to `bootstrap/app.php`.
- Created route files: `routes/shop.php`, `routes/shop-api.php`, `routes/blog.php`, `routes/api.php`.
- Rewrote `HandleInertiaRequests` to use feature providers instead of `ModuleRegistry`.
- Updated `AppServiceProvider` to register only Shop/Blog policies (Booking/Property policies removed).
- Created `FeatureRegistry` as a lightweight replacement for `ModuleRegistry`.
- Replaced `App\Modules\ModuleRegistry` → `App\Services\FeatureRegistry` in 10+ services.
- Fixed `NotificationTypeRegistry`, `NotificationService`, `FormattingService`, `GalleryService`, `RepeaterService`, and `DashboardService` to use `FeatureRegistry` (removed resolved-guards where needed).
- Added `getAllDashboardData()` to `FeatureRegistry`.
- Deleted `ModuleController.php`, `app/Models/Module.php`, `DebugModules.php`, `BookingPolicy.php`, `PropertyPolicy.php`, `PublishScheduledProperties.php`.
- Updated `PublishScheduledContent.php` to remove Property/Booking references.
- Updated `GenerateDemoData.php` imports and removed Property/Booking demo generation.
- Fixed all remaining `ModuleRegistry` references in `SyncModulePermissions`, `EmailSettingsController`, `AccountService`.
- Fixed all remaining `App\Models\Module` references in `SettingsController`, `SystemHealthService`.
- Rewrote `SyncModulePermissions` to use `FeatureRegistry`.
- Removed dead `maybeCreatePropertiesAndBookings()` method + `Property::delete()` from `GenerateDemoData.php`.
- Updated frontend: `vite.config.js` aliases, `resources/js/app.tsx` page imports, `tsconfig.json` paths.
- Deleted `resources/js/booking-widget.tsx`.
- Removed stale `ModuleSeeder.php`, module migrations, and Booking/Properties seeders/factories.
- Fixed double `/api` route prefix (removed `prefix('api')` from `shop-api.php`).
- Fixed `FeatureRegistry` constructor to use `app()->getProvider()` (ServiceProvider needs `$app` param).
- Fixed `HandleInertiaRequests` and `PermissionGateServiceProvider` same `app()` → `getProvider()` pattern.
- Updated 45 Inertia page paths in controllers: `Modules/Shop/*` → `shop/*`, `Modules/Posts/*` → `blog/*`.
- Updated 7 TSX `@modules-shop` imports to `@shop`.
- Removed `Route::put('/modules/{module}/toggle')` referencing deleted `ModuleController`.
- Deleted stale test files (Booking tests, PolicyEnforcementTest).
- Deleted stale seeders (ModuleSeeder, BookingSeeder, PropertyTermSeeder).
- Deleted stale factories (BookingFactory, PropertyFactory).

### Phase 5 — Frontend (Inertia + Vite)
- **Status: Done** — Updated `vite.config.js` aliases, `app.tsx` globs, `tsconfig.json` paths. Deleted `booking-widget.tsx`.

### Phase 6 — Cleanup + Verification
- **Status: Done** — All 156 tests passing, `vendor/bin/pint` clean, `npm run typecheck` clean, `npm run build` succeeds.

### Phase 7 — Documentation
- **Status: Done** — AGENTS.md rewritten with new structure, stale docs deleted (booking, properties, module-system), root-level scripts updated.

---

## 4. Key Decisions

| Decision | Rationale |
|----------|-----------|
| `App\Shop` flat namespace under `app/Shop/` | Minimizes churn vs deeper Laravel `App\Domain\Shop` structure |
| `App\Blog` namespace, keep `posts` route names | Keeps existing frontend URLs stable |
| Delete all module infrastructure | Removes dead weight; `FeatureRegistry` is the only module-like concept still needed for backward compatibility with services |
| `FeatureRegistry` aggregates providers | Lets `DashboardService`, `FormattingService`, etc. keep working without deeper rewrites |
| Routes loaded via route files (`routes/shop.php`) | Conventional Laravel loading, easier to audit than provider boot |
| `HandleInertiaRequests` iterates providers directly | Removes `ModuleRegistry` from the request lifecycle entirely |

---

## 5. Detailed Plan Phases

### Phase 0 — Preparation
- Audit all `App\Modules\*` references with grep.
- Map every module controller, model, view, route, and frontend page.
- Decide namespace targets (`App\Shop`, `App\Blog`).
- **Status: Done**

### Phase 1 — Move Shop + Blog to Core
- Create `app/Shop/` and `app/Blog/` directories.
- Move files, update namespaces, fix imports in 140+ PHP files.
- Create `ShopServiceProvider` and `BlogServiceProvider`.
- Register providers in `bootstrap/providers.php`.
- **Status: Done**

### Phase 2 — Remove Booking + Properties
- Delete `app/Modules/Booking/`, `app/Modules/Properties/`, `app/Modules/Contracts/`, `app/Modules/Traits/`.
- Delete `ModuleRegistry`, `ModuleLoader`, `BookableRegistry`.
- Delete `ModuleController`, `Module` model, `DebugModules` command.
- Delete `BookingPolicy`, `PropertyPolicy`.
- Delete `PublishScheduledProperties` command.
- **Status: Done (code moves complete; a few lingering references remain in consumer services)**

### Phase 3 — Replace ModuleRegistry with FeatureRegistry
- Create `App\Services\FeatureRegistry`.
- Update `DashboardService`, `FormattingService`, `GalleryService`, `RepeaterService`, `EmailSettingsController`, `NotificationService`, `NotificationTypeRegistry`, `GalleryService`, `AccountService`, `SystemHealthService`, `SettingsController`, `SyncModulePermissions`.
- Remove `app()->resolved(ModuleRegistry::class)` guards.
- **Status: Mostly done; remaining guard cleanups in progress**

### Phase 4 — Routing & Middleware
- Add `routes/api.php` + `api` middleware group to `bootstrap/app.php`.
- Create `routes/shop.php`, `routes/shop-api.php`, `routes/blog.php`, `routes/api.php`.
- Move all Shop/Blog routes out of providers into route files.
- Remove `ModuleEnabled` middleware and `module:` alias.
- Remove CSRF exceptions for booking routes.
- **Status: Done**

---

## 6. Critical Context

### Module System (Removed)
- `ModuleInterface` defined 53 methods including `getNavGroupLabel()`.
- `HasRoutes` trait provided `loadRoutes()`, `loadAdminRoutes()`, `loadPublicRoutes()`, `loadApiRoutes()`.
- `ModuleRegistry` was the central lookup for nav items, shared data, permissions, and formatting configs.
- `ModuleLoader` scanned `app/Modules/*/` for `{Name}Module.php`.

### Frontend Aliases (Before)
```
@modules-shop   → app/Modules/Shop/Resources/js/
@modules-posts  → app/Modules/Posts/Resources/js/
@modules-booking → app/Modules/Booking/Resources/js/
@modules-properties → app/Modules/Properties/Resources/js/
```

### Frontend Aliases (After)
```
@shop   → app/Shop/Resources/js/
@blog   → app/Blog/Resources/js/
```

### FeatureRegistry
Replaces `ModuleRegistry`. Aggregates `ShopServiceProvider` + `BlogServiceProvider`. Provides:
- `all()`, `get(string $slug)`
- `getAllNavItems()`, `getAllSharedData()`
- `getAllMetaFormattingConfigs()`, `getAllRepeaterConfigs()`, `getAllGalleryConfigs()`, `getAllProfileFieldConfigs()`
- `getAllNotificationTypes()`, `getAllEmailTemplates()`
- `getAllDashboardData()`
- `permissions()`
- `cleanupUserData(int $userId)`

### Services Consuming FeatureRegistry
- `DashboardService` • `FormattingService` • `GalleryService` • `RepeaterService`
- `NotificationService` • `NotificationTypeRegistry` • `EmailSettingsController`
- `PermissionGateServiceProvider` • `SettingsController` • `AccountService` • `SyncModulePermissions`

---

## 7. Verification (Complete)

```bash
php artisan test          # 155/155 passing
vendor/bin/pint           # clean
npm run typecheck          # clean
npm run build              # success
```

## 8. Remaining Work (Beyond Consolidation)

All consolidation tasks are complete. Future work should focus on new feature development as outlined in AGENTS.md.

---

*Last updated: 2026-07-08*
