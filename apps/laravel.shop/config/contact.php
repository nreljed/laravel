<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Store Contact Details
    |--------------------------------------------------------------------------
    |
    | Shared to every request as the `contact` Inertia prop and rendered on
    | the public /contact page. Owned by the project template kit — edit these
    | values to match the live store. Override per-environment via dotted keys
    | (e.g. CONTACT_EMAIL in .env) if preferred.
    |
    */

    'email' => env('CONTACT_EMAIL', 'support@shop.laravel.tunispromo.tn'),

    'phone' => env('CONTACT_PHONE', '+216 70 000 000'),

    'address' => env('CONTACT_ADDRESS', 'Avenue Habib Bourguiba, Tunis 1001, Tunisia'),

    'hours' => env('CONTACT_HOURS', 'Mon – Fri, 9:00 – 18:00'),
];
