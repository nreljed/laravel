<?php

return [
    'currency_symbol' => env('SHOP_CURRENCY_SYMBOL', '€'),

    'abandoned_cart' => [
        'enabled' => env('ABANDONED_CART_ENABLED', true),
        'hours_until_abandoned' => env('ABANDONED_CART_HOURS', 1),
        'sequence_hours' => [1, 24, 72],
    ],

    'hold_stock' => [
        'enabled' => env('HOLD_STOCK_ENABLED', true),
        'default_minutes' => (int) env('HOLD_STOCK_MINUTES', 60),
    ],

    'inventory' => [
        'default_low_stock_threshold' => (int) env('DEFAULT_LOW_STOCK_THRESHOLD', 5),
        'enabled' => true,
    ],

    'sale_scheduling' => [
        'enabled' => env('SALE_SCHEDULING_ENABLED', true),
        'normalize_to_day_boundaries' => true,
    ],

    'storefront' => [
        'hero' => [
            'title' => env('STORE_HERO_TITLE', 'Welcome to our store'),
            'subtitle' => env('STORE_HERO_SUBTITLE', 'Discover quality products hand-picked for you. Free shipping on orders over 50 EUR.'),
            'cta_label' => env('STORE_HERO_CTA', 'Shop now'),
        ],
    ],

    'checkout' => [
        'enable_guest_checkout' => env('CHECKOUT_GUEST_ENABLED', true),
        'require_company_field' => env('CHECKOUT_REQUIRE_COMPANY', false),
        'require_terms' => env('CHECKOUT_REQUIRE_TERMS', false),
        'default_country' => env('CHECKOUT_DEFAULT_COUNTRY', ''),
    ],

    'tax' => [
        'prices_include_tax' => env('SHOP_PRICES_INCLUDE_TAX', false),
        'based_on' => env('SHOP_TAX_BASED_ON', 'billing'),
        'shipping' => env('SHOP_TAX_ON_SHIPPING', false),
        'display' => env('SHOP_TAX_DISPLAY', 'exc'),
    ],

    'selling_locations' => [
        'mode' => env('SHOP_SELLING_MODE', 'all'),
        'base_country' => env('SHOP_BASE_COUNTRY', 'TN'),
        'countries' => [],
    ],

    'formatting' => [
        'currency_position' => env('SHOP_CURRENCY_POSITION', 'before'),
        'thousands_separator' => env('SHOP_THOUSANDS_SEPARATOR', ','),
        'decimal_separator' => env('SHOP_DECIMAL_SEPARATOR', '.'),
        'price_decimals' => (int) env('SHOP_PRICE_DECIMALS', 2),
        'weight_unit' => env('SHOP_WEIGHT_UNIT', 'kg'),
        'dimension_unit' => env('SHOP_DIMENSION_UNIT', 'cm'),
    ],

    'store' => [
        'name' => env('SHOP_STORE_NAME', ''),
        'email' => env('SHOP_STORE_EMAIL', ''),
        'address_line1' => env('SHOP_STORE_ADDRESS_LINE1', ''),
        'address_line2' => env('SHOP_STORE_ADDRESS_LINE2', ''),
        'city' => env('SHOP_STORE_CITY', ''),
        'postcode' => env('SHOP_STORE_POSTCODE', ''),
    ],
];
