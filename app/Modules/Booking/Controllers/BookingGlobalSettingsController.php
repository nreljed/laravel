<?php

namespace App\Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ModuleRegistry;
use App\Services\SettingsService;
use Inertia\Inertia;
use Illuminate\Http\Request;

class BookingGlobalSettingsController extends Controller
{
    private const OPTION_KEY = 'booking_options';

    public function __construct(
        protected ModuleRegistry $moduleRegistry,
    ) {}

    public function isModuleEnabled(): bool
    {
        return $this->moduleRegistry->isEnabled('bookings');
    }

    public function getOptions(): array
    {
        $options = SettingsService::getGroup('booking');

        $merged = array_merge(self::BOOKING_DEFAULTS, $options);

        $tz = config('app.timezone', 'UTC');
        $now = new \DateTime('now', new \DateTimeZone($tz));
        $offset = $now->getOffset();
        $sign = $offset >= 0 ? '+' : '-';
        $hours = sprintf('%02d', (int) (abs($offset) / 3600));
        $minutes = sprintf('%02d', (int) ((abs($offset) % 3600) / 60));
        $offsetStr = "UTC{$sign}{$hours}:{$minutes}";

        $merged['_site_timezone'] = $tz;
        $merged['_site_timezone_label'] = "{$tz} ({$offsetStr})";

        return $merged;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return SettingsService::get('booking_' . $key, $default);
    }

    public function updateFromRequest(array $data): array
    {
        $sanitized = $this->sanitizeBookingSettings($data);
        SettingsService::setMany($sanitized);
        return $this->getOptions();
    }

    public function getCurrency(?int $propertyId = null): string
    {
        if ($propertyId) {
            $property = \App\Modules\Properties\Models\Property::find($propertyId);
            if ($property && !empty($property->metadata['currency'])) {
                return strtoupper($property->metadata['currency']);
            }
        }
        if ($this->moduleRegistry->isEnabled('properties')) {
            $currency = SettingsService::get('property_default_currency');
            if (!empty($currency)) {
                return $currency;
            }
        }
        return 'USD';
    }

    protected const BOOKING_DEFAULTS = [
        'enable_auto_checkout' => true,
        'enable_no_show_auto_cancel' => true,
        'enable_guest_portal' => true,
        'enable_reviews' => true,
        'enable_damage_photos' => true,
        'grace_period_hours' => 24,
        'grace_period_advance_days' => 7,
        'allow_guest_cancellation_statuses' => ['pending', 'confirmed'],
        'allow_guest_modification_statuses' => ['pending', 'confirmed', 'checked_in'],
        'email_enable_confirmation' => true,
        'email_enable_pre_arrival' => true,
        'email_enable_checkin_instructions' => true,
        'email_enable_checkout_reminder' => true,
        'email_enable_post_stay' => true,
        'email_enable_cancellation' => true,
        'email_enable_modification_request' => true,
        'default_currency' => 'USD',
        'default_cancellation_policy' => 'flexible',
        'default_check_in_time' => '15:00',
        'default_check_out_time' => '11:00',
    ];

    protected function sanitizeBookingSettings(array $data): array
    {
        $allowed = array_keys(self::BOOKING_DEFAULTS);
        $sanitized = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (is_bool(self::BOOKING_DEFAULTS[$key])) {
                    $sanitized[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif (is_int(self::BOOKING_DEFAULTS[$key])) {
                    $sanitized[$key] = (int) $value;
                } elseif (is_array(self::BOOKING_DEFAULTS[$key])) {
                    $sanitized[$key] = is_array($value) ? $value : [];
                } else {
                    $sanitized[$key] = strip_tags((string) $value);
                }
            }
        }
        return $sanitized;
    }

    public function edit(): mixed
    {
        if (!$this->isModuleEnabled()) {
            return redirect('/dashboard/settings');
        }

        if (!auth()->user()->can('manage_options')) {
            return redirect('/dashboard');
        }

        $settings = $this->getOptions();

        return Inertia::render('Bookings/Settings/BookingGlobalSettings', [
            'settings' => $settings,
            'page' => [
                'title' => 'Booking Settings',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'href' => '/dashboard'],
                    ['label' => 'Settings', 'href' => '/dashboard/settings'],
                    ['label' => 'Booking Settings', 'href' => null],
                ],
            ],
        ]);
    }

    public function update(Request $request): mixed
    {
        if (!auth()->user()->can('manage_options')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->all();
        $updated = $this->updateFromRequest($data);

        return Inertia::render('Bookings/Settings/BookingGlobalSettings', [
            'settings' => $updated,
            'status' => 'Booking settings saved successfully!',
        ]);
    }
}
