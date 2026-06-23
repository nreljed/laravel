# Settings System

DB-driven settings with zero infrastructure — no config files to edit, no env vars to sync.

## Overview

Settings are stored in the `settings` database table and grouped by category (`general`, `email`, `security`, etc.).

```
settings table:
  key         → unique string identifier
  value       → text value (nullable)
  group       → category grouping

Example:
  key: "app_logo"
  value: "https://example.com/logo.png" or "/storage/logos/logo-1712345678.png"
  group: "general"
```

### Backend

| File | Role |
|------|------|
| `app/Models/Setting.php` | Eloquent model with `get()` / `set()` static helpers |
| `app/Services/SettingsService.php` | CRUD with cache layer |
| `app/Http/Controllers/SettingsController.php` | Admin settings page + logo upload endpoints |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shares `brand.logo`, `brand.name`, `app_url` to frontend |

### Frontend

| File | Role |
|------|------|
| `resources/js/Pages/Admin/Settings.tsx` | Tabbed settings page (routes to named `dashboard.admin.settings`) |
| `resources/js/Components/settings/sections/GeneralSection.tsx` | App name, URL, logo form |
| `resources/js/Components/settings/LogoUploader.tsx` | Logo file upload with preview + remove |
| `resources/js/Components/BrandLogo.tsx` | Dynamic logo component used across all layouts |
| `resources/js/Components/NavLogo.tsx` | Sidebar logo + "Visit Website" link |

## Logo Upload

### Flow

```
Settings → App Settings → Logo section
  └─ LogoUploader component
       ├─ User clicks "Upload" → file picker
       ├─ Client-side validation:
       │    ├─ Type: image/jpeg, image/png, image/gif, image/webp, image/svg+xml
       │    ├─ Size: max 10MB
       │    ├─ SVG: no dimension limits (vector)
       │    └─ Raster: min 100×100px, max 4096×4096px
       │         (reads dimensions via Image() before upload)
       ├─ POST /dashboard/admin/settings/logo (multipart/form-data)
       └─ Server-side (SettingsController::uploadLogo):
            ├─ Validates file type + dimensions (SVG excluded from dim check)
            ├─ Saves to: storage/app/public/logos/logo-{timestamp}.{ext}
            ├─ Deletes old logo file from disk
            ├─ Stores public URL in app_logo setting
            └─ Returns JSON { url: "/storage/logos/logo-12345.png?v=..." }

All pages pick up the change immediately (HandleInertiaRequests shares brand.logo on every request).
```

### Storage

```
storage/app/public/logos/logo-1712345678.png
         ↓ symlink (php artisan storage:link)
public/storage/logos/logo-1712345678.png
         ↓ served as
https://site.com/storage/logos/logo-1712345678.png
```

The `logos` filesystem disk is defined in `config/filesystems.php`:

```php
'logos' => [
    'driver' => 'local',
    'root' => storage_path('app/public/logos'),
    'url' => rtrim(env('APP_URL'), '/').'/storage/logos',
    'visibility' => 'public',
],
```

### Remove

```
LogoUploader → Remove button
  └─ DELETE /dashboard/admin/settings/logo
       └─ Deletes file from disk
       └─ Sets app_logo to ""
```

## Favicon

The uploaded logo doubles as the browser favicon automatically.

In `resources/views/app.blade.php`:

```html
<link rel="icon" href="{{ SettingsService::get('app_logo', asset('favicon.ico')) }}">
<link rel="apple-touch-icon" href="{{ SettingsService::get('app_logo', asset('favicon.ico')) }}">
```

- If a logo is uploaded → it's the favicon and apple-touch-icon
- If no logo → falls back to `public/favicon.ico` (default Laravel icon)
- The `app.blade.php` head renders on every page load, so a logo change takes effect immediately

### Recommendations

| Format | Favicon suitability | Notes |
|--------|-------------------|-------|
| **SVG** | ✅ Perfect | Vector, infinite scaling, works at 16px and 512px equally |
| PNG 512×512+ | ✅ Good | Downscales cleanly to all icon sizes |
| PNG < 100px | ❌ Too small | Blurry as favicon, tiny in sidebar |
| JPG | ⚠️ OK | No transparency support — white background around logo |
| Non-square | ⚠️ Uneven favicon | UI shows warning: *"not square, may not display evenly as favicon"* |

The UI shows the uploaded image dimensions and warns if the aspect ratio is not square.

## App URL

Stored in DB as `app_url` (settings `general` group), displayed in admin settings as "Application URL".

### Where it's used

| Place | Reads from | Effect |
|-------|-----------|--------|
| Sidebar "Visit Website" link | DB `app_url` settings | Links to the configured URL |
| Email templates | DB `app_url` first, falls back to request URL | Used for `siteUrl` in email data |
| Storage URLs | `.env` `APP_URL` (via filesystems config) | Not affected by DB setting |
| Route generation (`url()`, `route()`) | Request URL + `.env` `APP_URL` | Not affected by DB setting |

### Practical behavior

- Changing `app_url` in settings updates the sidebar "Visit Website" link and email template URLs
- It does NOT update `.env` — Laravel's internal URL generation continues to use the deployment URL
- For most shared hosting setups, the request URL is correct automatically, so the DB setting is only needed if email links or the sidebar link should point somewhere specific

## Brand Propagation

When `app_name` or `app_logo` is saved in settings:

```
SettingsController::update()
  └─ SettingsService::setMany() → settings table
       └─ SettingsService::clearCache()

Next Inertia request:
  └─ HandleInertiaRequests::share()
       └─ 'brand' => [
             'logo' => SettingsService::get('app_logo'),
             'name' => SettingsService::get('app_name'),
           ]
       └─ 'app_url' => SettingsService::get('app_url')

Frontend receives via usePage().props:
  ├─ Welcome.tsx           → BrandLogo + appName
  ├─ PublicLayout.tsx      → BrandLogo + appName
  ├─ GuestLayout.tsx       → BrandLogo
  ├─ app-sidebar.tsx       → NavLogo (reads brand from shared props)
  └─ app.blade.php         → favicon link (reads DB directly)
```

No cache — the settings are read fresh on every request (the query is a single indexed lookup by key).

## Routes

| Method | URL | Handler | Purpose |
|--------|-----|---------|---------|
| GET | `/dashboard/admin/settings` | `SettingsController@edit` | Show settings page |
| PUT | `/dashboard/admin/settings` | `SettingsController@update` | Save all settings |
| POST | `/dashboard/admin/settings/logo` | `SettingsController@uploadLogo` | Upload logo file |
| DELETE | `/dashboard/admin/settings/logo` | `SettingsController@deleteLogo` | Remove logo |
| PUT | `/dashboard/admin/settings/email` | `EmailSettingsController@update` | Email settings |
| POST | `/dashboard/admin/settings/email/test` | `EmailSettingsController@sendTest` | Test email |

## No-Infrastructure Guarantees

| Feature | How it works | Infrastructure |
|---------|-------------|----------------|
| Store settings | UPDATE/INSERT into `settings` table | None |
| Upload logo | File saved to `storage/app/public/logos/` | None (disk space) |
| Serve logo | `public/storage/logos/` symlink | `php artisan storage:link` (one-time) |
| Favicon | Blade reads DB setting on each page load | None |
| Cache | SettingsService layer with Cache facade | Optional (can be disabled) |
| Brand sync | Shared via Inertia props on every request | None |
