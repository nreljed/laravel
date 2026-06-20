<?php

namespace App\Services;

use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Cache;

class FormattingService
{
    protected static array $currencySymbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'TND' => 'د.ت',
        'MAD' => 'د.م.',
        'DZD' => 'DA',
        'EGP' => 'E£',
        'SAR' => 'ر.س',
        'AED' => 'د.إ',
        'QAR' => 'ر.ق',
        'KWD' => 'د.ك',
        'BHD' => '.د.ب',
        'OMR' => 'ر.ع',
        'JOD' => 'د.أ',
        'LYD' => 'ل.د',
        'SDG' => 'ج.س.',
        'SYP' => 'ل.س',
        'IQD' => 'ع.د',
        'IRR' => '﷼',
        'TRY' => '₺',
    ];

    protected static array $surfaceUnits = [
        'sq_ft'    => 'ft²',
        'sq_m'     => 'm²',
        'sq_yd'    => 'yd²',
        'sq_meter' => '㎡',
        'sq_feet'  => 'sq ft',
        'acres'    => 'ac',
        'hectares' => 'ha',
    ];

    public static function formatPrice(float|int|string $value, array $options = []): string
    {
        $price = floatval($value);

        if ($price === 0.0) {
            $emptyText = $options['empty_text'] ?? '';
            return $emptyText !== '' ? $emptyText : '';
        }

        $currency     = $options['currency'] ?? 'USD';
        $position     = $options['position'] ?? 'before';
        $thousandsSep = $options['thousands_sep'] ?? ',';
        $decimalSep   = $options['decimal_sep'] ?? '.';

        $symbol = self::$currencySymbols[$currency] ?? $currency;
        $formatted = self::formatNumber($price, $thousandsSep, $decimalSep);

        return $position === 'before'
            ? $symbol . $formatted
            : $formatted . ' ' . $symbol;
    }

    public static function formatSurface(float|int|string $value, array $options = []): string
    {
        $surface = floatval($value);

        if ($surface === 0.0) {
            return '';
        }

        $unit         = $options['unit'] ?? 'sq_ft';
        $thousandsSep = $options['thousands_sep'] ?? ',';
        $decimalSep   = $options['decimal_sep'] ?? '.';

        $unitLabel = self::$surfaceUnits[$unit] ?? 'ft²';
        $formatted = self::formatNumber($surface, $thousandsSep, $decimalSep);

        return $formatted . ' ' . $unitLabel;
    }

    public static function formatNumber(float|int|string $number, string $thousandsSep = ',', string $decimalSep = '.'): string
    {
        $number    = floatval($number);
        $formatted = number_format($number, 2, $decimalSep, $thousandsSep);

        $decimalPart = $decimalSep . '00';
        if (str_contains($formatted, $decimalPart)) {
            $formatted = str_replace($decimalPart, '', $formatted);
        }

        return $formatted;
    }

    public static function getCurrencySymbol(string $currencyCode): string
    {
        return self::$currencySymbols[$currencyCode] ?? $currencyCode;
    }

    public static function getSurfaceUnit(string $unitCode): string
    {
        return self::$surfaceUnits[$unitCode] ?? $unitCode;
    }

    public static function getCurrencyOptions(): array
    {
        $options = [];
        foreach (self::$currencySymbols as $code => $symbol) {
            $options[] = [
                'value'  => $code,
                'label'  => self::getCurrencyLabel($code, $symbol),
                'symbol' => $symbol,
            ];
        }
        return $options;
    }

    public static function getSurfaceUnitOptions(): array
    {
        $options = [];
        foreach (self::$surfaceUnits as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => self::getUnitLabel($code),
            ];
        }
        return $options;
    }

    public static function getCurrencyPositionOptions(): array
    {
        return [
            ['value' => 'before', 'label' => 'Before ($100)'],
            ['value' => 'after', 'label' => 'After (100$)'],
        ];
    }

    public static function getThousandsSeparatorOptions(): array
    {
        return [
            ['value' => ',', 'label' => 'Comma (1,000)'],
            ['value' => ' ', 'label' => 'Space (1 000)'],
            ['value' => '.', 'label' => 'Period (1.000)'],
        ];
    }

    public static function getDecimalSeparatorOptions(): array
    {
        return [
            ['value' => '.', 'label' => 'Period (100.50)'],
            ['value' => ',', 'label' => 'Comma (100,50)'],
        ];
    }

    public static function getDateFormatOptions(): array
    {
        return [
            ['value' => 'd/m/Y', 'label' => 'd/m/Y', 'example' => now()->format('d/m/Y')],
            ['value' => 'm/d/Y', 'label' => 'm/d/Y', 'example' => now()->format('m/d/Y')],
            ['value' => 'Y-m-d', 'label' => 'Y-m-d', 'example' => now()->format('Y-m-d')],
            ['value' => 'F j, Y', 'label' => 'F j, Y', 'example' => now()->format('F j, Y')],
            ['value' => 'j F Y', 'label' => 'j F Y', 'example' => now()->format('j F Y')],
            ['value' => 'l, F j, Y', 'label' => 'l, F j, Y', 'example' => now()->format('l, F j, Y')],
            ['value' => 'D, M j', 'label' => 'D, M j', 'example' => now()->format('D, M j')],
            ['value' => 'd.m.Y', 'label' => 'd.m.Y', 'example' => now()->format('d.m.Y')],
        ];
    }

    public static function getTimeFormatOptions(): array
    {
        return [
            ['value' => 'H:i', 'label' => 'H:i', 'example' => now()->format('H:i')],
            ['value' => 'g:i A', 'label' => 'g:i A', 'example' => now()->format('g:i A')],
            ['value' => 'H:i:s', 'label' => 'H:i:s', 'example' => now()->format('H:i:s')],
            ['value' => 'g:i a', 'label' => 'g:i a', 'example' => now()->format('g:i a')],
            ['value' => 'G:i', 'label' => 'G:i', 'example' => now()->format('G:i')],
        ];
    }

    public static function getDaysOfWeek(): array
    {
        return [
            ['value' => '0', 'label' => 'Sunday'],
            ['value' => '1', 'label' => 'Monday'],
            ['value' => '2', 'label' => 'Tuesday'],
            ['value' => '3', 'label' => 'Wednesday'],
            ['value' => '4', 'label' => 'Thursday'],
            ['value' => '5', 'label' => 'Friday'],
            ['value' => '6', 'label' => 'Saturday'],
        ];
    }

    protected static function getCurrencyLabel(string $code, string $symbol): string
    {
        $labels = [
            'USD' => 'US Dollar ($)',
            'EUR' => 'Euro (€)',
            'GBP' => 'British Pound (£)',
            'TND' => 'Tunisian Dinar (د.ت)',
            'MAD' => 'Moroccan Dirham (د.م.)',
            'DZD' => 'Algerian Dinar (DA)',
            'EGP' => 'Egyptian Pound (E£)',
            'SAR' => 'Saudi Riyal (ر.س)',
            'AED' => 'UAE Dirham (د.إ)',
            'QAR' => 'Qatari Riyal (ر.ق)',
            'KWD' => 'Kuwaiti Dinar (د.ك)',
            'BHD' => 'Bahraini Dinar (.د.ب)',
            'OMR' => 'Omani Rial (ر.ع)',
            'JOD' => 'Jordanian Dinar (د.أ)',
            'LYD' => 'Libyan Dinar (ل.د)',
            'SDG' => 'Sudanese Pound (ج.س.)',
            'SYP' => 'Syrian Pound (ل.س)',
            'IQD' => 'Iraqi Dinar (ع.د)',
            'IRR' => 'Iranian Rial (﷼)',
            'TRY' => 'Turkish Lira (₺)',
        ];

        return $labels[$code] ?? "{$code} ({$symbol})";
    }

    protected static function getUnitLabel(string $code): string
    {
        $labels = [
            'sq_ft'    => 'Square Feet (ft²)',
            'sq_m'     => 'Square Meters (m²)',
            'sq_yd'    => 'Square Yards (yd²)',
            'sq_meter' => 'Square Meters (㎡)',
            'sq_feet'  => 'Square Feet (sq ft)',
            'acres'    => 'Acres',
            'hectares' => 'Hectares',
        ];

        return $labels[$code] ?? $code;
    }

    public static function getModuleMetaConfigs(): array
    {
        return Cache::remember('formatting.module_configs', 3600, function () {
            if (!app()->resolved(ModuleRegistry::class)) {
                return [];
            }

            return app(ModuleRegistry::class)->getAllMetaFormattingConfigs();
        });
    }

    public static function formatRepeaterData(array $data, string $moduleSlug, array $globalOptions = []): array
    {
        $configs = self::getModuleMetaConfigs();
        $config = $configs[$moduleSlug] ?? [];

        $priceFields = $config['price_fields'] ?? [];
        $surfaceFields = $config['surface_fields'] ?? [];

        if (empty($priceFields) && empty($surfaceFields)) {
            return $data;
        }

        $options = array_merge(SettingsService::getFormattingOptions(), $globalOptions);

        return array_map(function ($row) use ($priceFields, $surfaceFields, $options) {
            foreach ($priceFields as $field) {
                if (isset($row[$field]) && $row[$field] !== '' && $row[$field] !== null) {
                    $row[$field . '_formatted'] = self::formatPrice($row[$field], $options);
                }
            }

            foreach ($surfaceFields as $field) {
                if (isset($row[$field]) && $row[$field] !== '' && $row[$field] !== null) {
                    $row[$field . '_formatted'] = self::formatSurface($row[$field], $options);
                }
            }

            return $row;
        }, $data);
    }

    public static function clearModuleConfigCache(): void
    {
        Cache::forget('formatting.module_configs');
    }
}
