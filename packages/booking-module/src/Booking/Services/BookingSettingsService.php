<?php

namespace Nreljed\BookingModule\Booking\Services;

use Nreljed\BookingModule\Booking\Enums\BookingStatus;
use Nreljed\Platform\Models\Setting;
use Nreljed\Platform\Services\SettingsService;

class BookingSettingsService
{
    public const GROUP = 'booking';

    /**
     * The single source of truth for booking setting keys, their types and
     * their defaults. Every persisted value is validated against this map.
     *
     * @var array<string, mixed>
     */
    protected const DEFAULTS = [
        // Booking Workflow / Guest Portal
        'enable_guest_portal' => true,
        'allow_guest_cancellation_statuses' => [BookingStatus::Pending->value, BookingStatus::Confirmed->value],
        'allow_guest_modification_statuses' => [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value],
        'grace_period_hours' => 24,
        'grace_period_advance_days' => 7,

        // Auto-Transition Controls
        'enable_auto_checkout' => true,
        'auto_checkout_grace_hours' => 24,
        'enable_no_show_auto_cancel' => true,
        'auto_cancel_no_show_hours' => 48,

        // Feature Toggles
        'enable_reviews' => true,
        'enable_damage_photos' => true,

        // Booking Defaults
        'default_currency' => 'USD',
        'default_cancellation_policy' => 'flexible',
        'default_booking_status' => BookingStatus::Pending->value,
        'default_check_in_time' => '15:00',
        'default_check_out_time' => '11:00',
        'default_min_nights' => 1,
        'default_max_nights' => 30,
        'default_preparation_days' => 1,
        'default_advance_notice_days' => 1,

        // Calendar
        'calendar_default_range' => 2,
        'calendar_max_range' => 12,

        // System
        'default_deposit_refund_days' => 7,
        'system_damage_claim_window_days' => 30,
        'system_payout_hold_hours' => 24,

        // Email Notifications
        'email_enable_confirmation' => true,
        'email_enable_new_booking' => true,
        'email_enable_pre_arrival' => true,
        'email_pre_arrival_days' => 3,
        'email_enable_checkin_instructions' => true,
        'email_enable_checkout_reminder' => true,
        'email_checkout_reminder_hours' => 24,
        'email_enable_post_stay' => true,
        'email_post_stay_delay_days' => 1,
        'email_enable_modification_request' => true,
        'email_enable_cancellation' => true,

        // Pricing Promotions
        'early_bird_discount_percent' => 0,
        'early_bird_days_before' => 30,
        'last_minute_discount_percent' => 0,
        'last_minute_days_within' => 7,
        'enable_nonrefundable_discount' => false,
        'nonrefundable_discount_percent' => 10,
    ];

    /**
     * All booking settings merged against defaults, cast to the default's type.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SettingsService::getGroup(self::GROUP);
        $merged = [];

        foreach (self::DEFAULTS as $key => $default) {
            $merged[$key] = $this->cast(array_key_exists($key, $stored) ? $stored[$key] : $default, $default);
        }

        return $merged;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            return $default;
        }

        $stored = SettingsService::getGroup(self::GROUP)[$key] ?? null;

        return $this->cast($stored ?? self::DEFAULTS[$key], self::DEFAULTS[$key]);
    }

    /**
     * Validate and persist a payload of booking settings.
     *
     * @return array<string, mixed> the freshly-merged settings after save
     */
    public function update(array $data): array
    {
        $validated = validator($data, $this->rules())->validate();

        foreach ($validated as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_array($value)) {
                $value = json_encode(array_values($value));
            }

            // Canonical core convention: unprefixed key scoped by the group column.
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'group' => self::GROUP],
            );
        }

        $this->clearCache();

        return $this->all();
    }

    /**
     * Laravel validation rules generated from the DEFAULTS map.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        $statuses = implode(',', BookingStatus::values());
        $rules = [];

        foreach (self::DEFAULTS as $key => $default) {
            if (is_array($default)) {
                $rules[$key] = 'nullable|array';
                $rules[$key.'.*'] = 'string|in:'.$statuses;

                continue;
            }

            $rules[$key] = match (true) {
                is_bool($default) => 'nullable|boolean',
                is_int($default) => $this->intRule($key),
                $key === 'default_currency' => 'nullable|string|size:3',
                $key === 'default_cancellation_policy' => 'nullable|string|in:flexible,moderate,limited,firm,strict,long_term,non_refundable',
                $key === 'default_booking_status' => 'nullable|string|in:'.$statuses,
                str_contains($key, '_time') => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
                default => 'nullable|string|max:255',
            };
        }

        return $rules;
    }

    public function clearCache(): void
    {
        SettingsService::clearCache();
    }

    private function intRule(string $key): string
    {
        $bounds = [
            'grace_period_hours' => [1, 72],
            'grace_period_advance_days' => [1, 30],
            'auto_checkout_grace_hours' => [0, 168],
            'auto_cancel_no_show_hours' => [0, 168],
            'default_min_nights' => [1, 365],
            'default_max_nights' => [1, 365],
            'default_preparation_days' => [0, 365],
            'default_advance_notice_days' => [0, 365],
            'calendar_default_range' => [1, 12],
            'calendar_max_range' => [1, 12],
            'default_deposit_refund_days' => [1, 90],
            'system_damage_claim_window_days' => [1, 90],
            'system_payout_hold_hours' => [0, 168],
            'email_pre_arrival_days' => [0, 30],
            'email_checkout_reminder_hours' => [1, 72],
            'email_post_stay_delay_days' => [0, 14],
            'early_bird_discount_percent' => [0, 100],
            'early_bird_days_before' => [1, 365],
            'last_minute_discount_percent' => [0, 100],
            'last_minute_days_within' => [1, 90],
            'nonrefundable_discount_percent' => [5, 20],
        ];

        [$min, $max] = $bounds[$key] ?? [0, 100000];

        return "nullable|integer|min:$min|max:$max";
    }

    private function cast(mixed $value, mixed $default): mixed
    {
        if (is_bool($default)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (is_int($default)) {
            return (int) $value;
        }

        if (is_array($default)) {
            if (is_array($value)) {
                return array_values($value);
            }

            $decoded = json_decode((string) $value, true);

            return is_array($decoded) ? array_values($decoded) : $default;
        }

        return $value === null ? $default : (string) $value;
    }
}
