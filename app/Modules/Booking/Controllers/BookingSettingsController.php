<?php

namespace App\Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Services\BookingCacheService;
use App\Modules\Properties\Models\Property;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingSettingsController extends Controller
{
    protected array $metaKeys = [
        'pets_allowed', 'pet_types', 'max_pets', 'pet_size_restrictions', 'pet_fee',
        'pet_deposit', 'pet_rules', 'safety_features', 'security_cameras_details',
        'instant_book', 'advance_notice_days', 'preparation_days', 'cancellation_policy',
        'cancellation_full_refund_days', 'cancellation_partial_refund_cutoff_days',
        'cancellation_partial_refund_percent',
        'long_term_min_nights', 'long_term_nonrefundable_nights',
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
        'host_cancellation_fee', 'host_cancellation_fee_within_7d',
        'default_status',
        'currency',
    ];

    protected array $booleanKeys = [
        'pets_allowed',
        'instant_book', 'require_damage_deposit', 'children_allowed', 'infants_allowed',
        'allow_unregistered_guests', 'allow_early_check_in', 'allow_late_check_out',
        'include_checkout_day', 'enable_damage_waiver',
    ];

    protected array $arrayKeys = [
        'pet_types', 'safety_features', 'weekend_days',
        'weekday_prices',
        'weekday_checkin_only', 'weekday_checkout_only',
    ];

    protected array $numberKeys = [
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

    public function __construct(
        protected BookingCacheService $cacheService,
    ) {}

    public function edit(int $propertyId): mixed
    {
        $property = Property::find($propertyId);
        if (! $property || $property->user_id !== auth()->id()) {
            return redirect()->route('properties.index')->withErrors(['auth' => 'Unauthorized']);
        }

        $metadata = $property->metadata ?? [];
        $defaults = $this->getFieldDefaults();

        $settings = [];
        foreach ($this->metaKeys as $key) {
            $value = $metadata[$key] ?? null;
            $isEmpty = $value === '' || $value === null || $value === false;

            if ($isEmpty) {
                $settings[$key] = $defaults[$key] ?? '';

                continue;
            }

            if (in_array($key, $this->booleanKeys)) {
                $settings[$key] = in_array($value, ['1', 1, 'true', true], true);
            } elseif (in_array($key, $this->arrayKeys)) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $settings[$key] = is_array($decoded) ? $decoded : ($value ? explode(',', $value) : []);
                } elseif (is_array($value)) {
                    $settings[$key] = $value;
                } else {
                    $settings[$key] = [];
                }
            } elseif (in_array($key, $this->numberKeys)) {
                $settings[$key] = (float) $value;
            } else {
                $settings[$key] = $value ?: ($defaults[$key] ?? '');
            }
        }

        $houseRules = $metadata['house_rules'] ?? [];
        $seasonalRates = $metadata['seasonal_rates'] ?? [];
        $lengthOfStayRates = $metadata['length_of_stay_rates'] ?? [];
        $lengthOfStayTiers = $metadata['length_of_stay_tiers'] ?? [];
        $unitConfig = $metadata['unit_config'] ?? [];

        return Inertia::render('Bookings/BookingSettings', [
            'property' => [
                'id' => $property->id,
                'title' => $property->name,
            ],
            'settings' => $settings,
            'house_rules' => $houseRules,
            'seasonal_rates' => $seasonalRates,
            'length_of_stay_rates' => $lengthOfStayRates,
            'length_of_stay_tiers' => $lengthOfStayTiers,
            'unit_config' => $unitConfig,
            'page' => ['title' => 'Booking Settings - '.$property->name],
        ]);
    }

    public function update(int $propertyId, Request $request): mixed
    {
        $property = Property::find($propertyId);
        if (! $property || $property->user_id !== auth()->id()) {
            return back()->withErrors(['auth' => 'Unauthorized']);
        }

        $data = $request->all();
        $defaults = $this->getFieldDefaults();
        $data = array_merge($defaults, $data);

        $metadata = $property->metadata ?? [];

        foreach ($this->metaKeys as $key) {
            $value = $data[$key] ?? null;

            if (in_array($key, $this->booleanKeys)) {
                $metadata[$key] = ($value === true || $value === 'true' || $value === 1 || $value === '1') ? '1' : '0';
            } elseif (in_array($key, $this->arrayKeys)) {
                if (is_array($value) && ! empty($value)) {
                    $metadata[$key] = json_encode($value);
                } else {
                    unset($metadata[$key]);
                }
            } elseif (in_array($key, $this->numberKeys)) {
                if ($value !== '' && $value !== null && is_numeric($value)) {
                    $metadata[$key] = (float) $value;
                } else {
                    unset($metadata[$key]);
                }
            } else {
                if (! empty($value)) {
                    $metadata[$key] = strip_tags($value);
                } else {
                    unset($metadata[$key]);
                }
            }
        }

        $houseRules = $request->input('house_rules', []);
        if (is_array($houseRules)) {
            $metadata['house_rules'] = $houseRules;
        } else {
            unset($metadata['house_rules']);
        }

        $seasonalRates = $request->input('seasonal_rates', []);
        if (is_array($seasonalRates)) {
            $metadata['seasonal_rates'] = $seasonalRates;
        } else {
            unset($metadata['seasonal_rates']);
        }

        $lengthOfStayRates = $request->input('length_of_stay_rates', []);
        if (is_array($lengthOfStayRates)) {
            $metadata['length_of_stay_rates'] = $lengthOfStayRates;
        } else {
            unset($metadata['length_of_stay_rates']);
        }

        $lengthOfStayTiers = $request->input('length_of_stay_tiers', []);
        if (is_array($lengthOfStayTiers)) {
            $metadata['length_of_stay_tiers'] = $lengthOfStayTiers;
        } else {
            unset($metadata['length_of_stay_tiers']);
        }

        $unitConfig = $request->input('unit_config', []);
        if (is_array($unitConfig)) {
            $metadata['unit_config'] = $unitConfig;
        } else {
            unset($metadata['unit_config']);
        }

        $property->update(['metadata' => $metadata]);

        $this->cacheService->bustPropertyCache($propertyId);

        return redirect()->back()->with('status', 'Booking settings saved successfully!');
    }

    protected function getFieldDefaults(): array
    {
        return [
            'pets_allowed' => '0',
            'pet_types' => '',
            'max_pets' => 0,
            'pet_size_restrictions' => '',
            'pet_fee' => 0,
            'pet_deposit' => 0,
            'pet_rules' => '',
            'safety_features' => '',
            'security_cameras_details' => '',
            'instant_book' => '0',
            'advance_notice_days' => 1,
            'preparation_days' => 1,
            'cancellation_policy' => 'flexible',
            'cancellation_full_refund_days' => 5,
            'cancellation_partial_refund_cutoff_days' => 1,
            'cancellation_partial_refund_percent' => 50,
            'long_term_min_nights' => 0,
            'long_term_nonrefundable_nights' => 0,
            'require_damage_deposit' => '0',
            'damage_deposit_amount' => 0,
            'damage_deposit_policy' => '',
            'deposit_collection_method' => 'at_booking',
            'deposit_type' => 'percentage',
            'deposit_percentage' => 0,
            'deposit_refund_days' => 7,
            'enable_damage_waiver' => '0',
            'damage_waiver_fee' => 0,
            'max_occupancy' => 0,
            'children_allowed' => '1',
            'infants_allowed' => '1',
            'smoking_allowed' => '',
            'neighborhood_description' => '',
            'parking_info' => '',
            'parking_spaces' => 0,
            'transit_info' => '',
            'local_recommendations' => '',
            'check_in_instructions' => '',
            'wifi_password' => '',
            'emergency_contact' => '',
            'allow_unregistered_guests' => '0',
            'unregistered_guest_limit' => 0,
            'unregistered_guest_rules' => '',
            'allow_early_check_in' => '0',
            'early_check_in_fee' => 0,
            'allow_late_check_out' => '0',
            'late_check_out_fee' => 0,
            'nightly_rate' => 0,
            'cleaning_fee' => 0,
            'service_fee' => 0,
            'service_fee_type' => 'percentage',
            'weekend_days' => ['Fri', 'Sat'],
            'weekend_multiplier' => 1,
            'weekday_prices' => '',
            'base_guests' => 1,
            'extra_guest_fee' => 0,
            'include_checkout_day' => '0',
            'earliest_bookable_date' => 0,
            'latest_bookable_date' => 0,
            'check_in_time' => '15:00',
            'check_out_time' => '11:00',
            'weekday_checkin_only' => '',
            'weekday_checkout_only' => '',
            'min_nights' => 1,
            'max_nights' => 0,
            'host_cancellation_fee' => 0,
            'host_cancellation_fee_within_7d' => 0,
            'default_status' => 'pending',
            'currency' => 'USD',
        ];
    }
}
