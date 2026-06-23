<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\ModuleRegistry;
use App\Modules\Properties\Models\Property;
use App\Services\SettingsService;

class BookingPropertyIntegrationService
{
    public static function isModuleActive(): bool
    {
        return app(ModuleRegistry::class)->isEnabled('bookings');
    }

    private static function getGlobalOptions(): array
    {
        return SettingsService::getGroup('booking');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getFieldDefaults(): array
    {
        $global = self::getGlobalOptions();

        return [
            'pets_allowed' => false,
            'pet_types' => [],
            'max_pets' => 1,
            'pet_size_restrictions' => '',
            'pet_fee' => 0,
            'pet_deposit' => 0,
            'pet_rules' => '',
            'safety_features' => [],
            'security_cameras_details' => '',
            'instant_book' => true,
            'advance_notice_days' => (int) ($global['default_advance_notice_days'] ?? 1),
            'preparation_days' => (int) ($global['default_preparation_days'] ?? 1),
            'cancellation_policy' => $global['default_cancellation_policy'] ?? 'flexible',
            'cancellation_full_refund_days' => 1,
            'cancellation_partial_refund_cutoff_days' => 0,
            'cancellation_partial_refund_percent' => 50,
            'host_cancellation_fee' => 50,
            'host_cancellation_fee_within_7d' => 100,
            'long_term_min_nights' => 28,
            'long_term_nonrefundable_nights' => 30,
            'require_damage_deposit' => false,
            'damage_deposit_amount' => 0,
            'damage_deposit_policy' => '',
            'deposit_collection_method' => 'credit_card_hold',
            'deposit_type' => 'fixed_amount',
            'deposit_percentage' => 20,
            'deposit_refund_days' => (int) ($global['default_deposit_refund_days'] ?? 7),
            'enable_damage_waiver' => false,
            'damage_waiver_fee' => 0,
            'max_occupancy' => 4,
            'children_allowed' => true,
            'infants_allowed' => true,
            'smoking_allowed' => 'not_allowed',
            'neighborhood_description' => '',
            'parking_info' => '',
            'parking_spaces' => 0,
            'transit_info' => '',
            'local_recommendations' => '',
            'check_in_instructions' => '',
            'wifi_password' => '',
            'emergency_contact' => '',
            'allow_unregistered_guests' => true,
            'unregistered_guest_limit' => 0,
            'unregistered_guest_rules' => '',
            'allow_early_check_in' => false,
            'early_check_in_fee' => 0,
            'allow_late_check_out' => false,
            'late_check_out_fee' => 0,
            'nightly_rate' => 0,
            'cleaning_fee' => 0,
            'service_fee' => 0,
            'service_fee_type' => 'fixed',
            'weekend_days' => ['fri', 'sat'],
            'weekend_multiplier' => 1.0,
            'base_guests' => 2,
            'extra_guest_fee' => 0,
            'include_checkout_day' => false,
            'earliest_bookable_date' => 0,
            'latest_bookable_date' => 12,
            'check_in_time' => $global['default_check_in_time'] ?? '15:00',
            'check_out_time' => $global['default_check_out_time'] ?? '11:00',
            'weekday_prices' => [],
            'weekday_checkin_only' => [],
            'weekday_checkout_only' => [],
            'min_nights' => (int) ($global['default_min_nights'] ?? 1),
            'max_nights' => (int) ($global['default_max_nights'] ?? 30),
            'default_status' => $global['default_booking_status'] ?? BookingStatus::Pending->value,
            'currency' => '',
        ];
    }

    /**
     * @return array{enabled: bool, settings: array<string, mixed>}|null
     */
    public static function getPropertyBookingData(int $propertyId): ?array
    {
        if (! self::isModuleActive()) {
            return null;
        }

        $property = Property::find($propertyId);
        if (! $property) {
            return null;
        }

        $meta = $property->metadata ?? [];
        $isEnabled = ! empty($meta['booking_enabled']);

        $settings = $isEnabled ? self::loadAllSettings($property) : [];

        return [
            'enabled' => $isEnabled,
            'settings' => $settings,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function savePropertyBookingData(int $propertyId, array $data): void
    {
        if (! self::isModuleActive()) {
            return;
        }

        $property = Property::findOrFail($propertyId);
        $meta = $property->metadata ?? [];

        $enabled = ! empty($data['enabled']);
        $meta['booking_enabled'] = $enabled;

        if (! $enabled) {
            $property->metadata = $meta;
            $property->save();

            return;
        }

        $settings = array_merge(self::getFieldDefaults(), $data['settings'] ?? []);

        $booleanKeys = [
            'pets_allowed',
            'instant_book', 'require_damage_deposit', 'children_allowed', 'infants_allowed',
            'allow_unregistered_guests', 'allow_early_check_in', 'allow_late_check_out',
            'include_checkout_day', 'enable_damage_waiver',
        ];

        $arrayKeys = [
            'pet_types', 'safety_features', 'weekend_days',
            'weekday_prices',
            'weekday_checkin_only', 'weekday_checkout_only',
        ];

        $numberKeys = [
            'max_pets', 'pet_fee', 'pet_deposit',
            'advance_notice_days', 'preparation_days',
            'damage_deposit_amount', 'max_occupancy', 'parking_spaces',
            'unregistered_guest_limit', 'early_check_in_fee', 'late_check_out_fee',
            'nightly_rate', 'cleaning_fee', 'service_fee', 'min_nights', 'max_nights',
            'weekend_multiplier', 'base_guests', 'extra_guest_fee',
            'earliest_bookable_date', 'latest_bookable_date',
            'host_cancellation_fee', 'host_cancellation_fee_within_7d',
        ];

        foreach ($settings as $key => $value) {
            if (in_array($key, $booleanKeys)) {
                $meta[$key] = ! empty($value);
            } elseif (in_array($key, $arrayKeys)) {
                $meta[$key] = is_array($value) ? $value : [];
            } elseif (in_array($key, $numberKeys)) {
                $meta[$key] = ($value !== '' && $value !== null && is_numeric($value)) ? (float) $value : 0;
            } else {
                $meta[$key] = ! empty($value) ? strip_tags((string) $value) : '';
            }
        }

        $repeaterFields = ['house_rules', 'seasonal_rates', 'length_of_stay_rates', 'length_of_stay_tiers', 'unit_config'];
        foreach ($repeaterFields as $field) {
            if (isset($settings[$field]) && is_array($settings[$field])) {
                $meta[$field] = $settings[$field];
            }
        }

        $property->metadata = $meta;
        $property->save();
    }

    public static function isPropertyBookable(int $propertyId): bool
    {
        if (! self::isModuleActive()) {
            return false;
        }

        $property = Property::find($propertyId);
        if (! $property) {
            return false;
        }

        $meta = $property->metadata ?? [];

        return ! empty($meta['booking_enabled']);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function loadAllSettings(Property $property): array
    {
        $defaults = self::getFieldDefaults();
        $meta = $property->metadata ?? [];
        $settings = [];

        $metaKeys = [
            'pets_allowed', 'pet_types', 'max_pets', 'pet_size_restrictions', 'pet_fee',
            'pet_deposit', 'pet_rules', 'safety_features', 'security_cameras_details',
            'instant_book', 'advance_notice_days', 'preparation_days', 'cancellation_policy',
            'cancellation_full_refund_days', 'cancellation_partial_refund_cutoff_days',
            'cancellation_partial_refund_percent',
            'long_term_min_nights', 'long_term_nonrefundable_nights',
            'host_cancellation_fee', 'host_cancellation_fee_within_7d',
            'require_damage_deposit', 'damage_deposit_amount', 'damage_deposit_policy',
            'deposit_collection_method', 'deposit_type', 'deposit_percentage',
            'deposit_refund_days', 'enable_damage_waiver', 'damage_waiver_fee',
            'max_occupancy', 'children_allowed', 'infants_allowed', 'smoking_allowed',
            'neighborhood_description', 'parking_info', 'parking_spaces', 'transit_info',
            'local_recommendations', 'check_in_instructions', 'wifi_password', 'emergency_contact',
            'allow_unregistered_guests', 'unregistered_guest_limit', 'unregistered_guest_rules',
            'allow_early_check_in', 'early_check_in_fee', 'allow_late_check_out', 'late_check_out_fee',
            'nightly_rate', 'cleaning_fee', 'service_fee', 'service_fee_type',
            'weekend_days', 'weekend_multiplier', 'weekday_prices',
            'base_guests', 'extra_guest_fee', 'include_checkout_day',
            'earliest_bookable_date', 'latest_bookable_date',
            'check_in_time', 'check_out_time',
            'weekday_checkin_only', 'weekday_checkout_only',
            'min_nights', 'max_nights',
            'default_status',
            'currency',
        ];

        $booleanKeys = [
            'pets_allowed',
            'instant_book', 'require_damage_deposit', 'children_allowed', 'infants_allowed',
            'allow_unregistered_guests', 'allow_early_check_in', 'allow_late_check_out',
            'include_checkout_day', 'enable_damage_waiver',
        ];

        $arrayKeys = [
            'pet_types', 'safety_features', 'weekend_days',
            'weekday_prices',
            'weekday_checkin_only', 'weekday_checkout_only',
        ];

        $numberKeys = [
            'max_pets', 'pet_fee', 'pet_deposit',
            'advance_notice_days', 'preparation_days',
            'damage_deposit_amount', 'max_occupancy', 'parking_spaces',
            'unregistered_guest_limit', 'early_check_in_fee', 'late_check_out_fee',
            'nightly_rate', 'cleaning_fee', 'service_fee', 'min_nights', 'max_nights',
            'weekend_multiplier', 'base_guests', 'extra_guest_fee',
            'earliest_bookable_date', 'latest_bookable_date',
            'cancellation_full_refund_days', 'cancellation_partial_refund_cutoff_days',
            'cancellation_partial_refund_percent',
            'long_term_min_nights', 'long_term_nonrefundable_nights',
            'deposit_percentage', 'deposit_refund_days', 'damage_waiver_fee',
            'host_cancellation_fee', 'host_cancellation_fee_within_7d',
        ];

        foreach ($metaKeys as $key) {
            $raw = $meta[$key] ?? null;

            if ($raw === '' || $raw === null || $raw === false) {
                $settings[$key] = $defaults[$key] ?? '';

                continue;
            }

            if (in_array($key, $booleanKeys)) {
                $settings[$key] = in_array($raw, ['1', 1, true, 'true'], true);
            } elseif (in_array($key, $arrayKeys)) {
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $settings[$key] = is_array($decoded) ? $decoded : ($raw ? explode(',', $raw) : []);
                } elseif (is_array($raw)) {
                    $settings[$key] = $raw;
                } else {
                    $settings[$key] = [];
                }
            } elseif (in_array($key, $numberKeys)) {
                $settings[$key] = (float) $raw;
            } else {
                $settings[$key] = $raw ?: ($defaults[$key] ?? '');
            }
        }

        $repeaterFields = ['house_rules', 'seasonal_rates', 'length_of_stay_rates', 'length_of_stay_tiers', 'unit_config'];
        foreach ($repeaterFields as $field) {
            $settings[$field] = $meta[$field] ?? [];
        }

        return $settings;
    }
}
