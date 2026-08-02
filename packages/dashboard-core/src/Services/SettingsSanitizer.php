<?php

namespace Nreljed\DashboardCore\Services;

class SettingsSanitizer
{
    public static function string(array $data, string $key, string $default = ''): string
    {
        return isset($data[$key]) ? trim((string) $data[$key]) : $default;
    }

    public static function bool(array $data, string $key, bool $default = false): bool
    {
        return isset($data[$key]) ? filter_var($data[$key], FILTER_VALIDATE_BOOLEAN) : $default;
    }

    public static function int(array $data, string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $value = isset($data[$key]) ? (int) $data[$key] : $default;
        if ($min !== null) {
            $value = max($min, $value);
        }
        if ($max !== null) {
            $value = min($max, $value);
        }

        return $value;
    }

    public static function float(array $data, string $key, float $default = 0.0, ?float $min = null, ?float $max = null): float
    {
        $value = isset($data[$key]) ? (float) $data[$key] : $default;
        if ($min !== null) {
            $value = max($min, $value);
        }
        if ($max !== null) {
            $value = min($max, $value);
        }

        return $value;
    }

    public static function enum(array $data, string $key, array $allowed, string $default): string
    {
        $value = $data[$key] ?? $default;

        return in_array((string) $value, $allowed, true) ? (string) $value : $default;
    }
}
