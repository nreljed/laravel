<?php

namespace Nreljed\Platform\Services;

/**
 * UploadProfiles - the single registry of upload configurations.
 *
 * Every upload type (avatar, brand logo, post/gallery images, media library)
 * is described by a named config array consumed by MediaService::save() so no
 * caller hardcodes its own rules.
 *
 * Config keys:
 *  - folder:            Storage subfolder under the public disk root.
 *  - maxFileSize:       Max upload size in bytes (client + server enforced).
 *  - allowedMimes:      Accepted MIME types (validated against real content).
 *  - outputExtension:   Extension of the stored file.
 *  - outputMimeType:    MIME type of the stored file.
 *  - outputQuality:     WebP compression quality.
 *  - filenamePrefix:    Prefix before _{timestamp}_{random}.{ext}.
 *  - title:             Media row title / attachment label.
 *  - organizeByMonth:   When true files are stored under {folder}/{Y}/{m}/
 *                       mirroring native month/year upload layouts.
 *  - variants:          Responsive widths to derive ([] disables variants).
 */
class UploadProfiles
{
    /**
     * User avatar upload profile.
     */
    public static function avatar(int $userId): array
    {
        return [
            'folder' => 'avatars',
            'maxFileSize' => 5 * 1024 * 1024, // 5MB
            'allowedMimes' => MediaService::IMAGE_MIMES,
            'outputExtension' => 'webp',
            'outputMimeType' => 'image/webp',
            'outputQuality' => 85,
            'filenamePrefix' => 'user_'.$userId,
            'title' => 'Avatar for user '.$userId,
            'organizeByMonth' => false,
            'variants' => [],
        ];
    }

    /**
     * App-wide brand logo upload profile.
     */
    public static function brandLogo(): array
    {
        return [
            'folder' => 'logos',
            'maxFileSize' => 2 * 1024 * 1024, // 2MB
            'allowedMimes' => MediaService::IMAGE_MIMES,
            'outputExtension' => 'webp',
            'outputMimeType' => 'image/webp',
            'outputQuality' => 90,
            'filenamePrefix' => 'brand',
            'title' => 'Brand Logo',
            'organizeByMonth' => false,
            'variants' => [],
        ];
    }

    /**
     * Contextual gallery image upload profile.
     */
    public static function gallery(string $context = 'post', int $parentId = 0): array
    {
        $folder = 'gallery-'.preg_replace('/[^a-z0-9_-]/i', '', $context);

        return [
            'folder' => $folder,
            'maxFileSize' => 50 * 1024 * 1024, // 50MB
            'allowedMimes' => MediaService::IMAGE_MIMES,
            'outputExtension' => 'webp',
            'outputMimeType' => 'image/webp',
            'outputQuality' => 85,
            'filenamePrefix' => $context.'_'.$parentId,
            'title' => ucfirst($context).' gallery image',
            'organizeByMonth' => true,
            'variants' => MediaService::RESPONSIVE_WIDTHS,
        ];
    }

    /**
     * Shared per-user Media Library upload profile.
     */
    public static function mediaLibrary(string $folder = 'media-library', int $userId = 0): array
    {
        return [
            'folder' => $folder,
            'maxFileSize' => 10 * 1024 * 1024, // 10MB
            'allowedMimes' => MediaService::IMAGE_MIMES,
            'outputExtension' => 'webp',
            'outputMimeType' => 'image/webp',
            'outputQuality' => 85,
            'filenamePrefix' => 'media_'.$userId,
            'title' => 'Media library image',
            'organizeByMonth' => true,
            'variants' => MediaService::RESPONSIVE_WIDTHS,
        ];
    }

    /**
     * Blog posts editor image upload profile (featured images, editor picks).
     */
    public static function posts(int $userId = 0): array
    {
        return [
            'folder' => 'posts',
            'maxFileSize' => 10 * 1024 * 1024, // 10MB
            'allowedMimes' => MediaService::IMAGE_MIMES,
            'outputExtension' => 'webp',
            'outputMimeType' => 'image/webp',
            'outputQuality' => 85,
            'filenamePrefix' => 'post_'.$userId,
            'title' => 'Post image',
            'organizeByMonth' => true,
            'variants' => MediaService::RESPONSIVE_WIDTHS,
        ];
    }

    /**
     * Shop product image upload profile.
     */
    public static function products(int $userId = 0): array
    {
        return [
            'folder' => 'products',
            'maxFileSize' => 10 * 1024 * 1024, // 10MB
            'allowedMimes' => MediaService::IMAGE_MIMES,
            'outputExtension' => 'webp',
            'outputMimeType' => 'image/webp',
            'outputQuality' => 85,
            'filenamePrefix' => 'product_'.$userId,
            'title' => 'Product image',
            'organizeByMonth' => true,
            'variants' => MediaService::RESPONSIVE_WIDTHS,
        ];
    }
}
