---
name: media-uploads
description: "Media upload handling for Cost Breaker 360. Activate when working with image/video uploads, MediaService, blog image upload/delete routes, the useImageUpload hook, FeaturedImageSelector, GalleryUploader, or when user mentions uploads, images, gallery, featured images, or media. Covers the WebP conversion pipeline, public-disk storage, path-traversal protection, and shared frontend upload helpers."
license: MIT
metadata:
  author: laravel
---

# Media Uploads

## MediaService

`Nreljed\Platform\Services\MediaService` is the single backend entry point for all file uploads (`upload()`, `delete()`, `getValidationRules()`).

### Image Processing Pipeline

```php
// vendor/nreljed/platform/src/Services/MediaService.php
const int MAX_DIMENSION = 1920; // downscale beyond this
const int QUALITY = 80;         // WebP encode quality
const int MAX_FILE_SIZE = 51200; // 50MB in KB
```

- Images are decoded with **Intervention Image** (GD driver), scaled down to max 1920px, and **re-encoded to WebP** (quality 80)
- Filename: `Str::random(40).'.webp'`
- Stored on the `public` disk under `{folder}/{filename}` → served via `Storage::url($path)`
- Videos are stored as-is: `storeAs($folder, Str::random(40).'.'.$ext, 'public')`

### Validation

`getValidationRules()` accepts image (`image/jpeg|png|gif|webp|avif|bmp|tiff`) and video (`video/mp4|webm|ogg|quicktime|x-msvideo|x-matroska`) MIME types, max 50MB.

### Delete Safety (Path Traversal)

`delete()` only removes files via `isSafePath()`:

```php
$path = str_replace('\\', '/', trim($path));
if ($path === '' || str_starts_with($path, '/')) return false;              // absolute paths
if (preg_match('#(^|/)\.\.(/|$)#', $path) === 1) return false;              // .. traversal
```

Never call `Storage::delete()` directly with user input — always route through `MediaService::delete()`.

## Backend Routes

| Route (name)                                                | Method | Permission        |
| ----------------------------------------------------------- | ------ | ----------------- |
| `POST dashboard/posts/upload-image` (`upload-image`)        | POST   | `posts.create`    |
| `DELETE dashboard/posts/delete-image` (`delete-image`)      | DELETE | `posts.edit`      |

Controllers call `app(MediaService::class)` directly (e.g. `PostController::uploadImage`).

## Frontend Helpers

### `@/lib/gallery-utils`

| Export                  | Purpose                                     |
| ----------------------- | ------------------------------------------- |
| `getCsrfToken()`        | CSRF token for standalone `fetch()`         |
| `jsonHeaders()`         | `X-Requested-With` + `X-CSRF-TOKEN` headers |
| `GALLERY_MAX_SIZE`      | 10MB frontend limit                         |
| `formatFileSize(bytes)` | Human-readable file size                    |
| `getImageUrl(image)`    | Resolve image URL                           |
| `createPreview(file)`   | Object URL preview                          |
| `getFileMetadata(file)` | Dimension/duration metadata                 |

### `useImageUpload` hook (`@/hooks/use-image-upload`)

`{ upload, remove, retry }` — takes `uploadUrl`, `deleteUrl`, optional `compress`. Used by:

- `FeaturedImageSelector` (single image) — `upload()` on pick, `remove()` on delete
- `GalleryUploader` (multiple) — batch upload with progress, per-item `retry()`, bulk delete confirm

Uploads go through standalone `fetch()` with CSRF headers (never Inertia forms).

## Sanitization

Post content is sanitized server-side via `HtmlSanitizer` in `PostController`:

- `content` / block `rich_text|code|heading` → `HtmlSanitizer::sanitize()`
- `featured_image` / `og_image` / block `media_url` → `HtmlSanitizer::sanitizeImageUrl()`
- block `embed` → `HtmlSanitizer::sanitizeUrl()`

## Tests

- `tests/Feature/Media/MediaUploadTest.php` — upload/destroy happy paths, validation failures (9 tests)
- `tests/Feature/Security/MediaPathTraversalTest.php` — traversal + absolute-path rejection (4 tests)

## Key Rules

- Always upload/delete through `MediaService` — never `Storage` directly in controllers
- Preserve `isSafePath()` semantics when touching delete logic
- Use `useImageUpload` + `gallery-utils` on the frontend (no axios)
- Run `php artisan storage:link` after deployment before uploads work
