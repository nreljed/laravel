<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class MediaService
{
    const int MAX_DIMENSION = 1920;

    const int QUALITY = 80;

    const int MAX_FILE_SIZE = 51200; // 50MB in KB

    const array IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'image/avif', 'image/bmp', 'image/tiff',
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

    public function upload(UploadedFile $file, string $folder): array
    {
        $mime = $file->getMimeType();

        if (in_array($mime, self::VIDEO_MIMES)) {
            return $this->storeVideo($file, $folder);
        }

        return $this->processImage($file, $folder);
    }

    /**
     * Delete a primary file together with its responsive variants.
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

        foreach ($this->variantPathsForPath($path) as $variant) {
            Cache::forget($this->variantCacheKey($variant));
        }
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

    private function processImage(UploadedFile $file, string $folder): array
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->decode($file->get());

        if ($image->width() > self::MAX_DIMENSION || $image->height() > self::MAX_DIMENSION) {
            $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);
        }

        $encoded = $image->encode(new WebpEncoder(quality: self::QUALITY));

        $filename = Str::random(40).'.webp';
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $path = $folder.'/'.$filename;

        Storage::disk('public')->put($path, (string) $encoded);

        $this->storeVariants($image, $folder, $base);

        return [
            'url' => Storage::url($path),
            'path' => $path,
        ];
    }

    private function storeVariants(ImageInterface $image, string $folder, string $base): void
    {
        foreach (self::RESPONSIVE_WIDTHS as $width) {
            $image->scaleDown($width);
            $encoded = $image->encode(new WebpEncoder(quality: self::QUALITY));
            Storage::disk('public')->put("{$folder}/{$base}-{$width}.webp", (string) $encoded);
        }
    }

    private function storeVideo(UploadedFile $file, string $folder): array
    {
        $filename = Str::random(40).'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'public');

        return [
            'url' => Storage::url($path),
            'path' => $path,
        ];
    }
}
