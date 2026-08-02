<?php

namespace Nreljed\DashboardCore\Services;

use Nreljed\DashboardCore\Models\RepeaterData;
use Nreljed\DashboardCore\Models\User;

class RepeaterService
{
    protected static ?array $configCache = null;

    protected static array $dataCache = [];

    protected static array $modelCache = [];

    public static function getConfig(): array
    {
        return static::$configCache ??= [];
    }

    public static function getRepeaters(?string $module = null): array
    {
        $configs = static::getConfig();

        if ($module !== null) {
            return $configs[$module] ?? [];
        }

        $all = [];

        foreach ($configs as $slug => $repeaters) {
            foreach ($repeaters as $key => $config) {
                $all[$slug.'.'.$key] = array_merge(['module' => $slug], $config);
            }
        }

        return $all;
    }

    public static function getRepeater(string $fieldKey, ?string $module = null): ?array
    {
        if ($module !== null) {
            $configs = static::getConfig();

            return $configs[$module][$fieldKey] ?? null;
        }

        foreach (static::getConfig() as $slug => $repeaters) {
            if (isset($repeaters[$fieldKey])) {
                return array_merge(['module' => $slug], $repeaters[$fieldKey]);
            }
        }

        return null;
    }

    public static function getJsonKey(string $fieldKey, ?string $module = null): ?string
    {
        $repeater = static::getRepeater($fieldKey, $module);

        return $repeater['json_key'] ?? $fieldKey;
    }

    public static function getAllJsonKeys(?string $module = null): array
    {
        $repeaters = static::getRepeaters($module);
        $keys = [];

        foreach ($repeaters as $key => $config) {
            $keys[] = $config['json_key'] ?? $key;
        }

        return array_values(array_unique($keys));
    }

    public static function getFields(string $fieldKey, ?string $module = null): array
    {
        $repeater = static::getRepeater($fieldKey, $module);

        return $repeater['fields'] ?? [];
    }

    public static function getFieldNames(string $fieldKey, ?string $module = null): array
    {
        $fields = static::getFields($fieldKey, $module);

        return array_keys($fields);
    }

    public static function isRepeater(string $fieldKey, ?string $module = null): bool
    {
        return static::getRepeater($fieldKey, $module) !== null;
    }

    public static function loadRepeaterData(int $modelId, string $fieldKey, ?string $module = null, ?array $allMeta = null): array
    {
        $cacheKey = $modelId.'.'.$fieldKey.'.'.($module ?? '*');

        if (array_key_exists($cacheKey, static::$dataCache)) {
            return static::$dataCache[$cacheKey];
        }

        $jsonKey = static::getJsonKey($fieldKey, $module);

        if ($allMeta !== null) {
            static::$dataCache[$cacheKey] = $allMeta[$jsonKey] ?? [];

            return static::$dataCache[$cacheKey];
        }

        $repeater = static::getRepeater($fieldKey, $module);

        if ($repeater && isset($repeater['model'])) {
            $modelClass = $repeater['model'];
            $model = $modelClass::find($modelId);

            if ($model && isset($model->meta)) {
                $meta = is_array($model->meta) ? $model->meta : ($model->meta->toArray() ?? []);
                static::$dataCache[$cacheKey] = $meta[$jsonKey] ?? [];

                return static::$dataCache[$cacheKey];
            }
        }

        $modelType = $repeater['model'] ?? null;

        $record = RepeaterData::where('model_type', $modelType ?? $fieldKey)
            ->where('model_id', $modelId)
            ->where('field_key', $fieldKey)
            ->first();

        static::$dataCache[$cacheKey] = $record ? ($record->data ?? []) : [];

        return static::$dataCache[$cacheKey];
    }

    public static function saveRepeaterData(int $modelId, string $fieldKey, array $data, ?string $module = null): bool
    {
        $jsonKey = static::getJsonKey($fieldKey, $module);
        $repeater = static::getRepeater($fieldKey, $module);
        $fields = static::getFields($fieldKey, $module);

        $sanitized = [];

        foreach ($data as $index => $row) {
            if (is_array($row)) {
                $sanitizedRow = [];

                foreach ($row as $fieldName => $value) {
                    $fieldConfig = $fields[$fieldName] ?? [];
                    $sanitizedRow[$fieldName] = static::sanitizeFieldValue($value, $fieldConfig);
                }

                $sanitized[] = $sanitizedRow;
            } else {
                $sanitized[] = $row;
            }
        }

        $cacheKey = $modelId.'.'.$fieldKey.'.'.($module ?? '*');
        static::$dataCache[$cacheKey] = $sanitized;

        if ($repeater && isset($repeater['model'])) {
            $modelClass = $repeater['model'];
            $model = $modelClass::find($modelId);

            if ($model && isset($model->meta)) {
                $meta = $model->meta ?? [];
                $meta[$jsonKey] = $sanitized;
                $model->meta = $meta;

                return $model->save();
            }

            if ($model && method_exists($model, 'saveRepeaterData')) {
                return $model->saveRepeaterData($fieldKey, $sanitized);
            }
        }

        $modelType = $repeater['model'] ?? null;

        $record = RepeaterData::firstOrNew([
            'model_type' => $modelType ?? User::class,
            'model_id' => $modelId,
            'field_key' => $fieldKey,
        ]);

        if ($module === null && $repeater && isset($repeater['module'])) {
            $module = $repeater['module'];
        }

        $record->module = $module;
        $record->data = $sanitized;

        return $record->save();
    }

    public static function deleteRepeaterData(int $modelId, string $fieldKey, ?string $module = null): bool
    {
        $cacheKey = $modelId.'.'.$fieldKey.'.'.($module ?? '*');
        unset(static::$dataCache[$cacheKey]);

        $repeater = static::getRepeater($fieldKey, $module);
        $jsonKey = static::getJsonKey($fieldKey, $module);

        if ($repeater && isset($repeater['model'])) {
            $modelClass = $repeater['model'];
            $model = $modelClass::find($modelId);

            if ($model && isset($model->meta)) {
                $meta = $model->meta ?? [];
                unset($meta[$jsonKey]);
                $model->meta = $meta;

                return $model->save();
            }
        }

        $modelType = $repeater['model'] ?? null;

        return (bool) RepeaterData::where('model_type', $modelType ?? $fieldKey)
            ->where('model_id', $modelId)
            ->where('field_key', $fieldKey)
            ->delete();
    }

    public static function sanitizeFieldValue(mixed $value, array $fieldConfig): mixed
    {
        $type = $fieldConfig['type'] ?? 'text';

        return match ($type) {
            'number' => is_numeric($value) ? (float) $value : 0.0,
            'url' => is_string($value) ? filter_var($value, FILTER_SANITIZE_URL) : '',
            'email' => is_string($value) ? filter_var($value, FILTER_SANITIZE_EMAIL) : '',
            'textarea' => is_string($value) ? strip_tags($value) : '',
            default => is_string($value) ? strip_tags($value) : (is_array($value) ? $value : (string) $value),
        };
    }

    public static function clearCache(): void
    {
        static::$configCache = null;
        static::$dataCache = [];
        static::$modelCache = [];
    }
}
