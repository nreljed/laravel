# Avatar System

## Overview

Users can upload a profile picture (avatar) via the profile page at `/dashboard/profile`. The system provides crop, zoom, rotation, and upload functionality, with the avatar displayed in the sidebar and profile pages.

---

## File Structure

```
app/
  Http/Controllers/AvatarController.php   # Upload + delete endpoints
  Traits/UserDataTrait.php                 # getAvatarUrlAttribute() accessor

config/filesystems.php                     # 'avatars' disk configuration

resources/js/
  Pages/Profile/Edit.tsx                   # Profile page (avatar state + fetch calls)
  Components/
    profile/sections/AvatarSection.tsx     # Section wrapper around AvatarUploader
    ui/avatar-uploader.tsx                 # File picker, validation, crop trigger
    ui/cropper-dialog.tsx                  # Crop/zoom/rotate dialog (react-image-crop)
    ui/avatar.tsx                          # shadcn/ui Avatar, AvatarImage, AvatarFallback
    nav-user.tsx                           # Sidebar avatar display
  hooks/use-auth.ts                        # useAuth() provides user.avatar URL

storage/app/public/avatars/                # Where files are stored on disk
public/storage -> storage/app/public       # Symlink for web access
```

---

## Storage

### Disk Configuration (`config/filesystems.php`)

```php
'avatars' => [
    'driver' => 'local',
    'root' => storage_path('app/public/avatars'),
    'url' => env('APP_URL') . '/storage/avatars',
    'visibility' => 'public',
],
```

Files are stored at `storage/app/public/avatars/` and served via the `public/storage` symlink at `/storage/avatars/`.

### File Naming Convention

```
avatar-{userId}-{timestamp}.webp
```

- `userId` — the user's ID for traceability
- `timestamp` — `time()` at upload, prevents collisions
- `.webp` — format from the cropper (256×256, 85% quality)

**Example**: `avatar-5-1782139945.webp`

---

## Frontend Flow

```
User clicks avatar circle
  → File picker opens (accepts jpeg, png, gif, webp, max 50MB)
    → Client-side validation (avatar-uploader.tsx:68-76)
      → FileReader reads as data URL
        → CropperDialog opens (react-image-crop)
          → User crops (circular, 1:1), zooms (1-3x), rotates (-180 to +180)
            → Canvas exports 256×256 WebP blob at 85% quality
              → AvatarUploader calls onUpload(blob)
                → Profile/Edit.tsx handleAvatarUpload():
                  1. Wraps blob in File object
                  2. fetch() POST to /dashboard/profile/avatar (FormData)
                  3. On success: setAvatarPreview(newUrl)
                  4. router.reload({ only: ['auth'] }) — refreshes sidebar
```

### Key Frontend Files

| File | Role |
|------|------|
| `avatar-uploader.tsx` | File validation, drag-drop, crop dialog trigger, preview, upload/delete buttons |
| `cropper-dialog.tsx` | Modal with react-image-crop, zoom slider, rotation slider, canvas export |
| `AvatarSection.tsx` | Thin Section wrapper with title "Profile Picture" |
| `Profile/Edit.tsx` | Manages `avatarPreview` state, `handleAvatarUpload` (fetch), `handleDeleteAvatar` |
| `nav-user.tsx` | Sidebar — renders `<AvatarImage>` with `user?.avatar`, fallback to initials |

---

## Backend Flow

### Upload (`AvatarController::upload()`)

1. **Validate**: `required`, `image`, `mimes:jpeg,png,jpg,gif,webp`, `max:51200`
2. **Save new file**: `$file->move($avatarDir, $filename)` to `storage/app/public/avatars/`
3. **Delete old file**: Only after new file is safely saved (protects against failed uploads losing the old avatar)
4. **Update DB**: `$user->update(['avatar' => $filename])`
5. **Return JSON**:
   ```json
   {
     "success": true,
     "avatar": { "url": "https://site.com/storage/avatars/avatar-5-1782139945.webp?v=1782139945" }
   }
   ```

### Delete (`AvatarController::destroy()`)

1. Delete file from disk via `Storage::disk('avatars')->delete()`
2. Set `users.avatar = null` in database
3. Return JSON success

### URL Generation (`UserDataTrait::getAvatarUrlAttribute()`)

```php
$url = Storage::disk('avatars')->url($this->avatar);

// In HTTP context, rewrites origin to match current request
// (handles mismatches between APP_URL and actual access URL)
```

The accessor dynamically rewrites the scheme/host to match the current request. In CLI/queue contexts, the configured `APP_URL` is used as-is.

---

## Routes

| Method | URL | Controller | Purpose |
|--------|-----|-----------|---------|
| `POST` | `/dashboard/profile/avatar` | `AvatarController::upload()` | Upload new avatar |
| `DELETE` | `/dashboard/profile/avatar` | `AvatarController::destroy()` | Remove avatar |

All routes are behind `auth` + `verified` middleware. The `POST` route is CSRF-exempt (token is sent in FormData body but the exemption exists due to fetch() usage).

---

## Content Security Policy

The CSP `img-src` directive is set to `'self' data: blob: https:` in `ContentSecurityPolicy.php`. Since the avatar URL is dynamically rewritten to use the current request's scheme and host, it matches `'self'` — no extra CSP entries are needed.

---

## Troubleshooting

| Symptom | Likely Cause | Fix |
|---------|-------------|-----|
| 500 error on upload | `$file->move()` failed (disk full, permissions) | Check `storage/logs/laravel.log` |
| Avatar not showing after upload | CSP blocking, or stale props | Verify CSP `img-src`, check browser console |
| Sidebar not updating | `router.reload` not firing | Check `only: ['auth']` in `handleAvatarUpload` |
| Old avatar still on disk after change | Upload failure after old file was deleted | Verify the delete-only-after-save logic in `AvatarController::upload()` |
| "Mixed Content" warning | Avatar URL scheme doesn't match page scheme | Check `UserDataTrait::getAvatarUrlAttribute()` — should auto-rewrite to request origin |
