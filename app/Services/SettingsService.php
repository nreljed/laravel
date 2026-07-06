<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'app_settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::set($key, $value);
        Cache::forget(self::CACHE_KEY);
    }

    public static function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }
        Cache::forget(self::CACHE_KEY);
    }

    public static function getGroup(string $group): array
    {
        return Cache::remember(self::CACHE_KEY."_{$group}", 3600, function () use ($group) {
            return Setting::where('group', $group)
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    public static function getAllGrouped(): array
    {
        return Cache::remember(self::CACHE_KEY.'_grouped', 3600, function () {
            return Setting::all()
                ->groupBy('group')
                ->map(fn ($items) => $items->pluck('value', 'key')->toArray())
                ->toArray();
        });
    }

    public static function getFormattingDefaults(): array
    {
        $settings = self::getGroup('formatting');

        return [
            'default_currency' => $settings['default_currency'] ?? 'USD',
            'currency_position' => $settings['currency_position'] ?? 'before',
            'price_empty_text' => $settings['price_empty_text'] ?? '',
            'surface_unit' => $settings['surface_unit'] ?? 'sq_ft',
            'default_country' => $settings['default_country'] ?? 'TN',
            'thousands_separator' => $settings['thousands_separator'] ?? ',',
            'decimal_separator' => $settings['decimal_separator'] ?? '.',
            'date_format' => $settings['date_format'] ?? 'Y-m-d',
            'time_format' => $settings['time_format'] ?? 'H:i',
            'start_of_week' => $settings['start_of_week'] ?? '0',
            'timezone' => $settings['timezone'] ?? 'UTC',
        ];
    }

    public static function getFormattingOptions(): array
    {
        $defaults = self::getFormattingDefaults();

        return [
            'currency' => $defaults['default_currency'],
            'position' => $defaults['currency_position'],
            'thousands_sep' => $defaults['thousands_separator'],
            'decimal_sep' => $defaults['decimal_separator'],
            'empty_text' => $defaults['price_empty_text'],
            'unit' => $defaults['surface_unit'],
            'default_country' => $defaults['default_country'],
        ];
    }

    public static function formatPrice(float|int|string $value): string
    {
        return app(FormattingService::class)->formatPrice($value, self::getFormattingOptions());
    }

    public static function formatSurface(float|int|string $value): string
    {
        return app(FormattingService::class)->formatSurface($value, self::getFormattingOptions());
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'_grouped');
        foreach (['general', 'registration', 'notifications', 'security', 'email', 'maintenance', 'localization', 'formatting', 'properties', 'booking', 'shop'] as $group) {
            Cache::forget(self::CACHE_KEY."_{$group}");
        }
    }
}
