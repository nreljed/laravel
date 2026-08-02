<?php

namespace Nreljed\DashboardCore\Services;

class FormattingService
{
    protected array $currencySymbols = [
        'USD' => '$',
        'EUR' => 'â‚¬',
        'GBP' => 'Â£',
        'TND' => 'Ø¯.Øª',
        'MAD' => 'Ø¯.Ù….',
        'DZD' => 'DA',
        'EGP' => 'EÂ£',
        'SAR' => 'Ø±.Ø³',
        'AED' => 'Ø¯.Ø¥',
        'QAR' => 'Ø±.Ù‚',
        'KWD' => 'Ø¯.Ùƒ',
        'BHD' => '.Ø¯.Ø¨',
        'OMR' => 'Ø±.Ø¹',
        'JOD' => 'Ø¯.Ø£',
        'LYD' => 'Ù„.Ø¯',
        'SDG' => 'Ø¬.Ø³.',
        'SYP' => 'Ù„.Ø³',
        'IQD' => 'Ø¹.Ø¯',
        'IRR' => 'ï·¼',
        'TRY' => 'â‚º',
    ];

    protected array $surfaceUnits = [
        'sq_ft' => 'ftÂ²',
        'sq_m' => 'mÂ²',
        'sq_yd' => 'ydÂ²',
        'sq_meter' => 'ãŽ¡',
        'sq_feet' => 'sq ft',
        'acres' => 'ac',
        'hectares' => 'ha',
    ];

    public function formatPrice(float|int|string $value, array $options = []): string
    {
        $price = floatval($value);

        if ($price === 0.0) {
            $emptyText = $options['empty_text'] ?? '';

            return $emptyText !== '' ? $emptyText : '';
        }

        $currency = $options['currency'] ?? 'USD';
        $position = $options['position'] ?? 'before';
        $thousandsSep = $options['thousands_sep'] ?? ',';
        $decimalSep = $options['decimal_sep'] ?? '.';

        $symbol = $this->currencySymbols[$currency] ?? $currency;
        $formatted = $this->formatNumber($price, $thousandsSep, $decimalSep);

        return $position === 'before'
            ? $symbol.$formatted
            : $formatted.' '.$symbol;
    }

    public function formatSurface(float|int|string $value, array $options = []): string
    {
        $surface = floatval($value);

        if ($surface === 0.0) {
            return '';
        }

        $unit = $options['unit'] ?? 'sq_ft';
        $thousandsSep = $options['thousands_sep'] ?? ',';
        $decimalSep = $options['decimal_sep'] ?? '.';

        $unitLabel = $this->surfaceUnits[$unit] ?? 'ftÂ²';
        $formatted = $this->formatNumber($surface, $thousandsSep, $decimalSep);

        return $formatted.' '.$unitLabel;
    }

    public function formatNumber(float|int|string $number, string $thousandsSep = ',', string $decimalSep = '.'): string
    {
        $number = floatval($number);
        $formatted = number_format($number, 2, $decimalSep, $thousandsSep);

        $decimalPart = $decimalSep.'00';
        if (str_contains($formatted, $decimalPart)) {
            $formatted = str_replace($decimalPart, '', $formatted);
        }

        return $formatted;
    }

    public function getCurrencySymbol(string $currencyCode): string
    {
        return $this->currencySymbols[$currencyCode] ?? $currencyCode;
    }

    public function getSurfaceUnit(string $unitCode): string
    {
        return $this->surfaceUnits[$unitCode] ?? $unitCode;
    }

    public function getCurrencyOptions(): array
    {
        $options = [];
        foreach ($this->currencySymbols as $code => $symbol) {
            $options[] = [
                'value' => $code,
                'label' => $this->getCurrencyLabel($code, $symbol),
                'symbol' => $symbol,
            ];
        }

        return $options;
    }

    public function getSurfaceUnitOptions(): array
    {
        $options = [];
        foreach ($this->surfaceUnits as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => $this->getUnitLabel($code),
            ];
        }

        return $options;
    }

    public function getCurrencyPositionOptions(): array
    {
        return [
            ['value' => 'before', 'label' => 'Before ($100)'],
            ['value' => 'after', 'label' => 'After (100$)'],
        ];
    }

    public function getThousandsSeparatorOptions(): array
    {
        return [
            ['value' => ',', 'label' => 'Comma (1,000)'],
            ['value' => ' ', 'label' => 'Space (1 000)'],
            ['value' => '.', 'label' => 'Period (1.000)'],
        ];
    }

    public function getDecimalSeparatorOptions(): array
    {
        return [
            ['value' => '.', 'label' => 'Period (100.50)'],
            ['value' => ',', 'label' => 'Comma (100,50)'],
        ];
    }

    public function getDateFormatOptions(): array
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

    public function getTimeFormatOptions(): array
    {
        return [
            ['value' => 'H:i', 'label' => 'H:i', 'example' => now()->format('H:i')],
            ['value' => 'g:i A', 'label' => 'g:i A', 'example' => now()->format('g:i A')],
            ['value' => 'H:i:s', 'label' => 'H:i:s', 'example' => now()->format('H:i:s')],
            ['value' => 'g:i a', 'label' => 'g:i a', 'example' => now()->format('g:i a')],
            ['value' => 'G:i', 'label' => 'G:i', 'example' => now()->format('G:i')],
        ];
    }

    public function getDaysOfWeek(): array
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

    protected function getCurrencyLabel(string $code, string $symbol): string
    {
        $labels = [
            'USD' => 'US Dollar ($)',
            'EUR' => 'Euro (â‚¬)',
            'GBP' => 'British Pound (Â£)',
            'TND' => 'Tunisian Dinar (Ø¯.Øª)',
            'MAD' => 'Moroccan Dirham (Ø¯.Ù….)',
            'DZD' => 'Algerian Dinar (DA)',
            'EGP' => 'Egyptian Pound (EÂ£)',
            'SAR' => 'Saudi Riyal (Ø±.Ø³)',
            'AED' => 'UAE Dirham (Ø¯.Ø¥)',
            'QAR' => 'Qatari Riyal (Ø±.Ù‚)',
            'KWD' => 'Kuwaiti Dinar (Ø¯.Ùƒ)',
            'BHD' => 'Bahraini Dinar (.Ø¯.Ø¨)',
            'OMR' => 'Omani Rial (Ø±.Ø¹)',
            'JOD' => 'Jordanian Dinar (Ø¯.Ø£)',
            'LYD' => 'Libyan Dinar (Ù„.Ø¯)',
            'SDG' => 'Sudanese Pound (Ø¬.Ø³.)',
            'SYP' => 'Syrian Pound (Ù„.Ø³)',
            'IQD' => 'Iraqi Dinar (Ø¹.Ø¯)',
            'IRR' => 'Iranian Rial (ï·¼)',
            'TRY' => 'Turkish Lira (â‚º)',
        ];

        return $labels[$code] ?? "{$code} ({$symbol})";
    }

    protected function getUnitLabel(string $code): string
    {
        $labels = [
            'sq_ft' => 'Square Feet (ftÂ²)',
            'sq_m' => 'Square Meters (mÂ²)',
            'sq_yd' => 'Square Yards (ydÂ²)',
            'sq_meter' => 'Square Meters (ãŽ¡)',
            'sq_feet' => 'Square Feet (sq ft)',
            'acres' => 'Acres',
            'hectares' => 'Hectares',
        ];

        return $labels[$code] ?? $code;
    }
}
