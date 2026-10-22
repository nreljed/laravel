<?php

namespace Nreljed\MarketingModule\Support;

use Nreljed\Platform\Services\MediaService;

/**
 * The media contract between the composer, storage and the adapters.
 *
 * The gallery control posts URLs and returns `{url, alt, order, size}` items;
 * the adapters consume `{url, path, mime, title}`. Normalising once here keeps
 * both sides stable and gives the upload endpoint a single place to decide
 * which formats are accepted.
 */
final class PostMedia
{
    /**
     * Storage folder for post media, scoped from config.
     */
    public static function folder(): string
    {
        $folder = (string) config('marketing.media.subdirectory', 'social');

        return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $folder) === 1 ? $folder : 'social';
    }

    /**
     * The upload profile. Social platforms reject WebP, so post media is
     * stored as JPEG rather than the platform default.
     *
     * @return array<string, mixed>
     */
    public static function profile(int $userId = 0): array
    {
        return [
            'folder' => self::folder(),
            'maxFileSize' => (int) config('marketing.media.max_file_size', 10 * 1024 * 1024),
            'allowedMimes' => MediaService::IMAGE_MIMES,
            'outputExtension' => 'jpg',
            'outputMimeType' => 'image/jpeg',
            'outputQuality' => (int) config('marketing.media.output_quality', 85),
            'filenamePrefix' => 'social_'.$userId,
            'title' => 'Social post image',
            'organizeByMonth' => true,
            // JPEG profiles do not derive WebP responsive variants.
            'variants' => [],
        ];
    }

    /**
     * Normalise the composer's gallery value into the adapter's media shape.
     *
     * @param  array<int|string, mixed>  $items
     * @return list<array{url: string, path: string, mime: string, title: string|null, size: int, order: int}>
     */
    public static function normalize(array $items): array
    {
        $media = [];

        foreach ($items as $item) {
            $url = is_array($item) ? ($item['url'] ?? null) : $item;

            if (! is_string($url)) {
                continue;
            }

            $url = trim($url);

            if ($url === '' || strlen($url) > 2048) {
                continue;
            }

            $path = self::pathFromUrl($url);

            if ($path === null) {
                continue;
            }

            $mime = is_array($item) && is_string($item['mime'] ?? null) && $item['mime'] !== ''
                ? $item['mime']
                : self::mimeFromPath($path);

            $title = is_array($item)
                ? ($item['title'] ?? $item['alt'] ?? $item['caption'] ?? null)
                : null;

            $size = is_array($item) && is_numeric($item['size'] ?? null)
                ? max(0, (int) $item['size'])
                : 0;

            $media[] = [
                'url' => $url,
                'path' => $path,
                'mime' => $mime,
                'title' => is_string($title) ? $title : null,
                'size' => $size,
                'order' => count($media),
            ];
        }

        return $media;
    }

    /**
     * Whether a stored path lives in this feature's media folder.
     */
    public static function isManagedPath(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return str_starts_with($path, self::folder().'/');
    }

    /**
     * Disk path for a stored or absolute URL.
     */
    public static function pathFromUrl(string $url): ?string
    {
        $parsed = parse_url($url, PHP_URL_PATH);

        if (! is_string($parsed) || $parsed === '') {
            return null;
        }

        $path = ltrim($parsed, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    private static function mimeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '')) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            default => 'image/jpeg',
        };
    }
}
