<?php

namespace Nreljed\Platform\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Nreljed\Platform\Models\Media;

class MediaService
{
    const int MAX_DIMENSION = 1920;

    const int QUALITY = 80;

    const int MAX_FILE_SIZE = 51200; // 50MB in KB

    const array IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'image/avif', 'image/bmp', 'image/tiff',
    ];

    /**
     * File extensions the orphan scanner considers "media" when reconciling
     * disk contents against `media` rows.
     */
    const array ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tiff',
    ];

    const array VIDEO_MIMES = [
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
        'video/x-msvideo', 'video/x-matroska',
    ];

    /**
     * Responsive widths generated for every image upload, in the order they
     * are derived (descending so each scale-down step reuses the previous).
     */
    const array RESPONSIVE_WIDTHS = [1200, 800, 400];

    const int VARIANT_CACHE_TTL = 300;

    public function getValidationRules(): array
    {
        $allMimes = array_merge(
            preg_replace('/^image\//', '', self::IMAGE_MIMES),
            preg_replace('/^video\//', '', self::VIDEO_MIMES)
        );

        return [
            'file' => 'required|file|mimetypes:'.implode(',', array_merge(self::IMAGE_MIMES, self::VIDEO_MIMES)).'|max:'.self::MAX_FILE_SIZE,
        ];
    }

    /**
     * Backwards-compatible uploads: flat folder, responsive variants, 50MB
     * image+video allowlist. New code should use save() with an UploadProfile.
     */
    public function upload(UploadedFile $file, string $folder): array
    {
        $profile = [
            'folder' => $folder,
            'maxFileSize' => self::MAX_FILE_SIZE * 1024, // 50MB
            'allowedMimes' => array_merge(self::IMAGE_MIMES, self::VIDEO_MIMES),
            'outputExtension' => 'webp',
            'outputMimeType' => 'image/webp',
            'outputQuality' => self::QUALITY,
            'filenamePrefix' => 'upload',
            'title' => $folder,
            'organizeByMonth' => false,
            'variants' => self::RESPONSIVE_WIDTHS,
        ];

        return $this->save($file, $profile);
    }

    /**
     * The single upload engine for every media type. Validates by real
     * content (never the client MIME), enforces the profile's size limit,
     * re-encodes images to WebP, respects month/year organization, and
     * derives the profile's responsive variants.
     *
     * @param  array<string, mixed>  $profile  an UploadProfiles::*() config
     * @return array{path: string, url: string, filename: string, size: int, width: int|null, height: int|null, mime_type: string}
     *
     * @throws ValidationException When the file fails the profile's size or content rules.
     */
    public function save(UploadedFile $file, array $profile): array
    {
        $relativeFolder = $this->resolveRelativeFolder($profile);

        $maxBytes = (int) ($profile['maxFileSize'] ?? self::MAX_FILE_SIZE * 1024);
        $allowed = $profile['allowedMimes'] ?? array_merge(self::IMAGE_MIMES, self::VIDEO_MIMES);

        if ($file->getSize() > $maxBytes) {
            $maxMb = round($maxBytes / 1024 / 1024, 1);
            throw ValidationException::withMessages(['file' => "File too large. Maximum {$maxMb}MB allowed."]);
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'Invalid file type. Only JPG, PNG, GIF, and WebP allowed.']);
        }

        if (in_array($mime, self::VIDEO_MIMES, true)) {
            return $this->storeRaw($file, $relativeFolder, $profile);
        }

        return $this->processImage($file, $relativeFolder, $profile);
    }

    /**
     * File validation rules derived from an upload profile.
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, string>
     */
    public function rulesFor(array $profile): array
    {
        $mimes = $profile['allowedMimes'] ?? array_merge(self::IMAGE_MIMES, self::VIDEO_MIMES);
        $maxBytes = (int) ($profile['maxFileSize'] ?? self::MAX_FILE_SIZE * 1024);

        return [
            'file' => 'required|file|mimetypes:'.implode(',', $mimes).'|max:'.(int) ceil($maxBytes / 1024),
        ];
    }

    /**
     * Whether a folder segment is safe for scoping media queries and uploads.
     */
    public function isSafeFolder(string $folder): bool
    {
        return $folder !== '' && (bool) preg_match('/^[a-z0-9][a-z0-9_-]*$/', $folder);
    }

    /**
     * Delete a primary file together with its responsive variants, and remove
     * any persisted Media rows pointing at those paths.
     */
    public function delete(string $path): void
    {
        if (! $this->isSafePath($path)) {
            return;
        }

        $paths = array_merge([$path], $this->variantPathsForPath($path));

        foreach ($paths as $file) {
            Storage::disk('public')->delete($file);
        }

        foreach ($paths as $file) {
            Media::where('path', $file)->delete();
        }

        foreach ($this->variantPathsForPath($path) as $variant) {
            Cache::forget($this->variantCacheKey($variant));
        }
    }

    /**
     * Reconcile a folder's disk contents against its `media` rows and report
     * orphans:
     *
     *  1. `records`       — Media rows whose primary file is missing on disk
     *                       (dangling rows that break thumbnails).
     *  2. `orphan_files`  — files on disk that have no Media row (manual
     *                       copies, aborted uploads, leftover seeds).
     *
     * Responsive variants (`-{1200|800|400}.webp`) are only reported as orphan
     * files when their base primary file is also gone — a variant of a
     * recorded/existing primary is never an orphan.
     *
     * @param  string  $folder  storage folder to scan (e.g. "posts")
     * @param  int|null  $userId  scope record checks to one user; null = all
     * @param  bool  $includeOrphanFiles  expose unrecorded disk files (managers only)
     * @return array{folder: string, records: list<array{id: int, path: string, filename: string, size: int}>, orphan_files: list<array{path: string, filename: string, size: int}>, totals: array{missing_records: int, orphan_files: int}}
     */
    public function scanFolder(string $folder, ?int $userId = null, bool $includeOrphanFiles = true): array
    {
        $empty = [
            'folder' => $folder,
            'records' => [],
            'orphan_files' => [],
            'totals' => ['missing_records' => 0, 'orphan_files' => 0],
        ];

        if (! $this->isSafeFolder($folder)) {
            return $empty;
        }

        $query = Media::where('folder', $folder);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $records = $query
            ->get(['id', 'path', 'filename', 'size'])
            ->filter(fn (Media $media) => ! Storage::disk('public')->exists($media->path))
            ->values()
            ->map(fn (Media $media) => [
                'id' => $media->id,
                'path' => $media->path,
                'filename' => $media->filename,
                'size' => $media->size,
            ])
            ->all();

        $orphanFiles = $includeOrphanFiles ? $this->orphanFiles($folder) : [];

        return [
            'folder' => $folder,
            'records' => $records,
            'orphan_files' => $orphanFiles,
            'totals' => [
                'missing_records' => count($records),
                'orphan_files' => count($orphanFiles),
            ],
        ];
    }

    /**
     * Files on disk under a folder that have no corresponding `media` row.
     *
     * @return list<array{path: string, filename: string, size: int}>
     */
    public function orphanFiles(string $folder): array
    {
        if (! $this->isSafeFolder($folder)) {
            return [];
        }

        $known = Media::where('folder', $folder)->pluck('path')->flip();

        $variantSuffixes = array_map(
            fn (int $width) => "-{$width}.webp",
            self::RESPONSIVE_WIDTHS
        );

        $orphans = [];

        foreach (Storage::disk('public')->allFiles($folder) as $path) {
            $path = str_replace('\\', '/', $path);

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?? '');
            if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            if (isset($known[$path])) {
                continue;
            }

            $primary = null;
            foreach ($variantSuffixes as $suffix) {
                if (str_ends_with($path, $suffix)) {
                    $primary = substr($path, 0, -strlen($suffix)).'.webp';
                    break;
                }
            }

            if ($primary !== null) {
                if (isset($known[$primary]) || Storage::disk('public')->exists($primary)) {
                    continue;
                }
            }

            $orphans[] = [
                'path' => $path,
                'filename' => basename($path),
                'size' => Storage::disk('public')->size($path),
            ];
        }

        usort($orphans, fn (array $a, array $b) => strcmp($a['path'], $b['path']));

        return $orphans;
    }

    /**
     * Delete Media rows whose primary file is missing on disk (the record is
     * already broken — nothing on disk is touched). Variant caches are cleared.
     */
    public function deleteMissingRecords(string $folder, ?int $userId = null): int
    {
        if (! $this->isSafeFolder($folder)) {
            return 0;
        }

        $query = Media::where('folder', $folder);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $gone = $query
            ->get(['id', 'path'])
            ->filter(fn (Media $media) => ! Storage::disk('public')->exists($media->path));

        foreach ($gone as $media) {
            foreach ($this->variantPathsForPath($media->path) as $variant) {
                Cache::forget($this->variantCacheKey($variant));
            }

            $media->delete();
        }

        return $gone->count();
    }

    /**
     * Delete physical files on disk that have no `media` row, together with
     * any stray responsive variants and caches (via delete()).
     */
    public function deleteOrphanFiles(string $folder): int
    {
        if (! $this->isSafeFolder($folder)) {
            return 0;
        }

        $removed = 0;

        foreach ($this->orphanFiles($folder) as $orphan) {
            $this->delete($orphan['path']);
            $removed++;
        }

        return $removed;
    }

    /**
     * Persist a Media library row from an upload result.
     *
     * @param  array<string, mixed>  $result  output of upload()
     */
    public function recordMedium(int $userId, string $folder, array $result, ?string $parentType = null, ?int $parentId = null): Media
    {
        return Media::create([
            'user_id' => $userId,
            'folder' => $folder,
            'parent_type' => $parentType,
            'parent_id' => $parentId,
            'path' => $result['path'],
            'url' => $result['url'],
            'filename' => $result['filename'],
            'title' => $result['filename'],
            'mime_type' => $result['mime_type'],
            'size' => $result['size'],
            'width' => $result['width'] ?? null,
            'height' => $result['height'] ?? null,
        ]);
    }

    /**
     * Resolve a `srcset` string for a stored image URL, listing the responsive
     * variants that actually exist (legacy uploads may not have any).
     *
     * @return string|null e.g. "/storage/posts/abc-800.webp 800w, /storage/posts/abc-1200.webp 1200w"
     */
    public function responsiveSrcset(?string $url): ?string
    {
        $path = $this->storedUrlToPath($url);

        if (! $path) {
            return null;
        }

        $base = pathinfo($path, PATHINFO_DIRNAME).'/'.pathinfo($path, PATHINFO_FILENAME);

        $parts = [];

        foreach (self::RESPONSIVE_WIDTHS as $width) {
            $variantPath = $base.'-'.$width.'.webp';

            $exists = Cache::remember(
                $this->variantCacheKey($variantPath),
                self::VARIANT_CACHE_TTL,
                fn () => Storage::disk('public')->exists($variantPath)
            );

            if ($exists) {
                $parts[] = Storage::url($variantPath)." {$width}w";
            }
        }

        if ($parts === []) {
            return null;
        }

        $parts[] = Storage::url($path).' '.self::MAX_DIMENSION.'w';

        return implode(', ', $parts);
    }

    /**
     * Storage paths for the responsive variants of a primary storage path.
     *
     * @return list<string>
     */
    public function variantPathsForPath(string $path): array
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION) ?? '') !== 'webp') {
            return [];
        }

        $base = pathinfo($path, PATHINFO_DIRNAME).'/'.pathinfo($path, PATHINFO_FILENAME);

        return array_map(fn (int $width) => "{$base}-{$width}.webp", self::RESPONSIVE_WIDTHS);
    }

    /**
     * Reject path traversal and absolute paths so callers can never delete
     * files outside the configured disk root.
     */
    protected function isSafePath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_starts_with($path, '/')) {
            return false;
        }

        if (preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            return false;
        }

        return true;
    }

    /**
     * Convert a stored URL (relative or absolute) into a disk path.
     */
    protected function storedUrlToPath(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $prefix = '/storage/';

        if (str_starts_with($url, $prefix)) {
            return substr($url, strlen($prefix));
        }

        if (str_starts_with($url, 'http')) {
            $parsed = parse_url($url, PHP_URL_PATH);

            return $parsed && str_starts_with($parsed, $prefix)
                ? substr($parsed, strlen($prefix))
                : null;
        }

        return $this->isSafePath($url) ? $url : null;
    }

    protected function variantCacheKey(string $variantPath): string
    {
        return 'media:variant:'.$variantPath;
    }

    /**
     * Resolve the storage-relative folder for a profile, appending the
     * {Y}/{m} month layout when organizeByMonth is set, and ensure it exists.
     */
    protected function resolveRelativeFolder(array $profile): string
    {
        $folder = rtrim((string) ($profile['folder'] ?? 'media'), '/');

        if (! empty($profile['organizeByMonth'])) {
            $folder .= '/'.now()->format('Y').'/'.now()->format('m');
        }

        Storage::disk('public')->makeDirectory($folder);

        return $folder;
    }

    /**
     * Build a stored filename from a profile: {prefix}_{timestamp}_{random}.{ext}.
     */
    protected function buildFilename(array $profile): string
    {
        $prefix = preg_replace('/[^a-z0-9_-]/i', '', (string) ($profile['filenamePrefix'] ?? 'media'));
        $extension = preg_replace('/[^a-z0-9]/i', '', (string) ($profile['outputExtension'] ?? 'webp'));
        $stamp = (int) (microtime(true) * 1000);

        return sprintf('%s_%d_%s.%s', $prefix, $stamp, Str::random(6), $extension);
    }

    private function processImage(UploadedFile $file, string $relativeFolder, array $profile): array
    {
        $manager = new ImageManager(new Driver);

        try {
            $image = $manager->decode($file->get());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'Invalid file type. Only JPG, PNG, GIF, and WebP allowed.']);
        }

        $maxDimension = (int) ($profile['maxDimension'] ?? self::MAX_DIMENSION);
        if ($image->width() > $maxDimension || $image->height() > $maxDimension) {
            $image->scaleDown($maxDimension, $maxDimension);
        }

        $encoded = $this->encodeImage($image, $profile);

        $filename = $this->buildFilename($profile);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $path = $relativeFolder.'/'.$filename;

        Storage::disk('public')->put($path, (string) $encoded);

        $this->storeVariants($image, $relativeFolder, $base, $profile);

        return [
            'url' => Storage::url($path),
            'path' => $path,
            'filename' => $filename,
            'size' => strlen((string) $encoded),
            'width' => $image->width(),
            'height' => $image->height(),
            'mime_type' => $profile['outputMimeType'] ?? 'image/webp',
        ];
    }

    /**
     * Encode an image in the profile's declared output format.
     *
     * WebP stays the default; a profile that declares `outputExtension` of
     * jpg/jpeg or png gets that format instead (social platforms such as
     * Instagram and Pinterest reject WebP).
     */
    private function encodeImage(ImageInterface $image, array $profile): string
    {
        $quality = (int) ($profile['outputQuality'] ?? self::QUALITY);
        $extension = strtolower(preg_replace(
            '/[^a-z0-9]/i',
            '',
            (string) ($profile['outputExtension'] ?? 'webp'),
        ));

        $encoder = match ($extension) {
            'jpg', 'jpeg' => new JpegEncoder(quality: $quality),
            'png' => new PngEncoder(quality: $quality),
            default => new WebpEncoder(quality: $quality),
        };

        return (string) $image->encode($encoder);
    }

    private function storeVariants(ImageInterface $image, string $relativeFolder, string $base, array $profile): void
    {
        // Responsive variants are WebP-only today: the orphan scanner and
        // delete() locate them by the `-{width}.webp` suffix, so deriving
        // them beside a JPEG/PNG primary would leave untrackable files.
        $extension = strtolower(preg_replace(
            '/[^a-z0-9]/i',
            '',
            (string) ($profile['outputExtension'] ?? 'webp'),
        ));

        if ($extension !== 'webp') {
            return;
        }

        $widths = $profile['variants'] ?? self::RESPONSIVE_WIDTHS;
        $quality = (int) ($profile['outputQuality'] ?? self::QUALITY);

        foreach ($widths as $width) {
            $image->scaleDown($width);
            $encoded = $image->encode(new WebpEncoder(quality: $quality));
            Storage::disk('public')->put("{$relativeFolder}/{$base}-{$width}.webp", (string) $encoded);
        }
    }

    private function storeRaw(UploadedFile $file, string $relativeFolder, array $profile): array
    {
        $extension = preg_replace('/[^a-z0-9]/i', '', (string) ($profile['outputExtension'] ?? $file->getClientOriginalExtension()));
        $filename = $this->buildFilename(array_merge($profile, ['outputExtension' => $extension]));
        $path = $relativeFolder.'/'.$filename;

        Storage::disk('public')->put($path, $file->get());

        return [
            'url' => Storage::url($path),
            'path' => $path,
            'filename' => $filename,
            'size' => $file->getSize(),
            'width' => null,
            'height' => null,
            'mime_type' => (string) $file->getMimeType(),
        ];
    }
}
