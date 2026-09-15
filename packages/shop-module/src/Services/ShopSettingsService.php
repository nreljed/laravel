<?php

namespace Nreljed\ShopModule\Services;

use Nreljed\Platform\Services\SettingsService;

class ShopSettingsService
{
    private const string GROUP = 'shop';

    public function all(): array
    {
        $s = SettingsService::getGroup(self::GROUP);

        $defaults = [
            'store_name' => '',
            'store_email' => '',
            'store_address_line1' => '',
            'store_address_line2' => '',
            'store_city' => '',
            'store_postcode' => '',
            'base_country' => 'TN',
            'selling_locations_mode' => 'all',
            'selling_locations' => [],
            'default_product_status' => 'draft',
            'low_stock_threshold' => 5,
            'hold_stock_enabled' => true,
            'hold_stock_minutes' => 60,
            'backorder_default' => 'do_not_allow',
            'inventory_enabled' => true,
            'show_out_of_stock' => true,
            'currency' => 'EUR',
            'currency_symbol' => '€',
            'currency_position' => 'before',
            'thousands_separator' => ',',
            'decimal_separator' => '.',
            'price_decimals' => 2,
            'prices_include_tax' => false,
            'tax_based_on' => 'billing',
            'tax_shipping' => false,
            'tax_display' => 'exc',
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
            'enable_reviews' => true,
            'enable_wishlist' => true,
            'auto_complete_orders' => false,
            'abandoned_cart_enabled' => true,
            'abandoned_cart_hours_until_abandoned' => 1,
            'abandoned_cart_sequence_hours' => '1,24,72',
            'sale_scheduling_enabled' => true,
            'sale_scheduling_normalize_to_day_boundaries' => true,
            'enable_guest_checkout' => true,
            'require_company_field' => false,
            'require_terms' => false,
            'checkout_default_country' => '',
        ];

        $out = [];
        foreach ($defaults as $key => $default) {
            $out[$key] = array_key_exists($key, $s) ? $this->cast($s[$key], $default) : $default;
        }

        return $out;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function baseCountry(): string
    {
        return $this->get('base_country', 'TN') ?: 'TN';
    }

    public function guestCheckoutEnabled(): bool
    {
        return (bool) $this->get('enable_guest_checkout', true);
    }

    public function requireCompanyField(): bool
    {
        return (bool) $this->get('require_company_field', false);
    }

    public function requireTerms(): bool
    {
        return (bool) $this->get('require_terms', false);
    }

    public function checkoutDefaultCountry(): string
    {
        return (string) $this->get('checkout_default_country', '');
    }

    public function pricesIncludeTax(): bool
    {
        return (bool) $this->get('prices_include_tax', false);
    }

    public function taxBasedOn(): string
    {
        return $this->get('tax_based_on', 'billing') === 'shipping' ? 'shipping' : 'billing';
    }

    public function taxShipping(): bool
    {
        return (bool) $this->get('tax_shipping', false);
    }

    public function taxDisplay(): string
    {
        $display = $this->get('tax_display', 'exc');

        return in_array($display, ['inc', 'exc', 'both'], true) ? $display : 'exc';
    }

    public function currencyFormat(): array
    {
        $position = $this->get('currency_position', 'before');
        if (! in_array($position, ['before', 'after', 'before_space', 'after_space'], true)) {
            $position = 'before';
        }

        return [
            'position' => $position,
            'thousands_separator' => (string) $this->get('thousands_separator', ','),
            'decimal_separator' => (string) $this->get('decimal_separator', '.'),
            'decimals' => max(0, min(4, (int) $this->get('price_decimals', 2))),
        ];
    }

    /**
     * Whether the given ISO-2 country is allowed by the selling locations policy.
     */
    public function isCountryAllowed(?string $country): bool
    {
        $country = strtoupper((string) $country);
        if ($country === '') {
            return true;
        }

        $mode = $this->get('selling_locations_mode', 'all');
        $locations = array_map('strtoupper', (array) $this->get('selling_locations', []));

        return match ($mode) {
            'specific' => in_array($country, $locations, true),
            'except' => ! in_array($country, $locations, true),
            default => true,
        };
    }

    public function clearCache(): void
    {
        SettingsService::clearCache();
    }

    private function cast(mixed $value, mixed $default): mixed
    {
        if (is_bool($default)) {
            return in_array($value, [true, '1', 1, 'true'], true);
        }

        if (is_int($default)) {
            return (int) $value;
        }

        if (is_array($default)) {
            if (is_array($value)) {
                return $value;
            }
            $decoded = json_decode((string) $value, true);

            return is_array($decoded) ? $decoded : $default;
        }

        return (string) $value;
    }
}
