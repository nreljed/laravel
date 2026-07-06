<?php

namespace App\Modules\Booking\Services;

use App\Modules\BookableRegistry;
use App\Services\FormattingService;
use App\Services\SettingsService;

class BookingCurrencyService
{
    public static function getCurrency(int $propertyId): string
    {
        $property = app(BookableRegistry::class)->resolve('properties', $propertyId);

        if ($property && ! empty($property->currency)) {
            return strtoupper($property->currency);
        }

        $bookingSettings = $property?->booking_settings;
        if (! empty($bookingSettings['currency'])) {
            return strtoupper($bookingSettings['currency']);
        }

        $global = SettingsService::get('booking_currency', '');
        if ($global) {
            return strtoupper($global);
        }

        $formatting = SettingsService::getFormattingOptions();
        if (! empty($formatting['currency'])) {
            return $formatting['currency'];
        }

        return 'USD';
    }

    public static function formatPrice(float|int|string $value, int $propertyId): string
    {
        $currency = self::getCurrency($propertyId);
        $formatting = SettingsService::getFormattingOptions();

        return app(FormattingService::class)->formatPrice($value, array_merge($formatting, [
            'currency' => $currency,
        ]));
    }

    public static function getSupportedCurrencies(): array
    {
        return [
            'USD' => 'US Dollar ($)',
            'EUR' => 'Euro (€)',
            'GBP' => 'British Pound (£)',
            'CAD' => 'Canadian Dollar (C$)',
            'AUD' => 'Australian Dollar (A$)',
            'CHF' => 'Swiss Franc (Fr)',
            'JPY' => 'Japanese Yen (¥)',
            'CNY' => 'Chinese Yuan (¥)',
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
            'TRY' => 'Turkish Lira (₺)',
        ];
    }
}
