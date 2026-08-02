<?php

namespace Nreljed\DashboardCore\Services;

class GalleryService
{
    public static function getConfig(string $module): ?array
    {
        $all = self::getAllConfigs();

        return $all[$module] ?? null;
    }

    public static function getAllConfigs(): array
    {
        if (! app()->resolved(FeatureRegistry::class)) {
            return [];
        }

        return app(FeatureRegistry::class)->getAllGalleryConfigs();
    }

    public static function getGalleryConfig(string $module, string $galleryKey): ?array
    {
        $config = self::getConfig($module);

        return $config[$galleryKey] ?? null;
    }

    public static function getJsonKey(string $module, string $galleryKey): string
    {
        $config = self::getGalleryConfig($module, $galleryKey);

        return $config['json_key'] ?? $galleryKey;
    }

    public static function getThumbnailKey(string $module, string $galleryKey): string
    {
        $config = self::getGalleryConfig($module, $galleryKey);

        return $config['thumbnail_key'] ?? $galleryKey.'_thumbnail_url';
    }

    public static function getGalleryKeys(string $module): array
    {
        $config = self::getConfig($module);

        if ($config === null) {
            return [];
        }

        return array_keys($config);
    }

    public static function loadGalleryData(object $model, string $module, string $galleryKey = 'gallery'): array
    {
        $jsonKey = self::getJsonKey($module, $galleryKey);

        if (method_exists($model, 'getMeta')) {
            return $model->getMeta($jsonKey) ?? [];
        }

        if (isset($model->meta) && is_array($model->meta)) {
            return $model->meta[$jsonKey] ?? [];
        }

        if (isset($model->meta) && is_string($model->meta)) {
            $decoded = json_decode($model->meta, true);

            return $decoded[$jsonKey] ?? [];
        }

        return [];
    }

    public static function saveGalleryData(object $model, string $module, array $data, string $galleryKey = 'gallery'): bool
    {
        $jsonKey = self::getJsonKey($module, $galleryKey);

        $filtered = array_values(array_filter($data, function ($item) {
            if (is_array($item)) {
                return ! empty($item['url']) || ! empty($item['src']) || ! empty($item['path']);
            }

            return ! empty($item);
        }));

        if (method_exists($model, 'setMeta')) {
            $model->setMeta($jsonKey, $filtered);

            return $model->save();
        }

        if (isset($model->meta) && is_array($model->meta)) {
            $meta = $model->meta;
            $meta[$jsonKey] = $filtered;
            $model->meta = $meta;

            return $model->save();
        }

        $meta = is_string($model->meta ?? '{}') ? json_decode($model->meta ?? '{}', true) : [];
        $meta[$jsonKey] = $filtered;
        $model->meta = json_encode($meta);

        return $model->save();
    }

    public static function updateThumbnail(object $model, string $module, string $galleryKey = 'gallery', array $galleryData = []): void
    {
        if (empty($galleryData)) {
            $galleryData = self::loadGalleryData($model, $module, $galleryKey);
        }

        $thumbnailKey = self::getThumbnailKey($module, $galleryKey);

        $thumbnailUrl = '';

        if (! empty($galleryData)) {
            $first = $galleryData[0];

            if (is_array($first)) {
                $thumbnailUrl = $first['url'] ?? $first['src'] ?? $first['path'] ?? '';
            } elseif (is_string($first)) {
                $thumbnailUrl = $first;
            }
        }

        if (method_exists($model, 'setMeta')) {
            $model->setMeta($thumbnailKey, $thumbnailUrl);
            $model->save();

            return;
        }

        if (isset($model->meta) && is_array($model->meta)) {
            $meta = $model->meta;
            $meta[$thumbnailKey] = $thumbnailUrl;
            $model->meta = $meta;
            $model->save();

            return;
        }

        $meta = is_string($model->meta ?? '{}') ? json_decode($model->meta ?? '{}', true) : [];
        $meta[$thumbnailKey] = $thumbnailUrl;
        $model->meta = json_encode($meta);
        $model->save();
    }

    public static function getAjaxActions(string $module): array
    {
        $config = self::getConfig($module);

        if ($config === null) {
            return [];
        }

        $actions = [];

        foreach ($config as $galleryConfig) {
            if (isset($galleryConfig['upload_action'])) {
                $actions[] = $galleryConfig['upload_action'];
            }

            if (isset($galleryConfig['delete_action'])) {
                $actions[] = $galleryConfig['delete_action'];
            }
        }

        return array_values(array_unique($actions));
    }

    public static function clearCache(): void {}
}
