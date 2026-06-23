<?php

namespace App\Modules\Booking\Services;

use App\Modules\Properties\Models\Property;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;

class BookingPricingService
{
    const PRICE_TTL = 3600;

    /** @var array<int, mixed> */
    private static array $propertyMetaCache = [];

    /**
     * @return array<string, mixed>
     */
    public function calculate(int $propertyId, string $checkIn, string $checkOut, int $guests, bool $hasPets = false, ?string $unitId = null, ?string $createdAt = null, bool $isNonRefundable = false, ?array $allMeta = null): array
    {
        $cacheVersion = $this->getCacheVersion($propertyId);
        $cacheKey = 'booking_price_'.$propertyId.'_'.$checkIn.'_'.$checkOut.'_'.$guests.'_'.($hasPets ? '1' : '0').'_'.($unitId ?? 'null').'_'.($createdAt ?? 'null').'_nr'.($isNonRefundable ? '1' : '0');
        $cached = Cache::get($cacheKey);
        if ($cached !== null && isset($cached['_version']) && $cached['_version'] === $cacheVersion) {
            return $cached;
        }

        $nights = $this->calculateNights($checkIn, $checkOut);
        if ($nights <= 0) {
            return $this->emptyBreakdown($propertyId, $this->getCurrency($propertyId));
        }

        $property = Property::findOrFail($propertyId);
        $meta = $allMeta ?? ($property->metadata ?? []);
        $m = function (string $key, mixed $default = null) use ($meta) {
            return array_key_exists($key, $meta) ? $meta[$key] : $default;
        };

        $unitService = new BookingUnitService;
        $effectiveRate = null;

        if ($unitId) {
            $unit = $unitService->getUnitById($propertyId, $unitId);
            if ($unit) {
                $effectiveRate = $unitService->getUnitEffectiveRate($propertyId, $unit);
            }
        }

        $baseRate = $effectiveRate ?? (float) ($m('nightly_rate') ?: ($property->price ?? 0));
        $cleaningFee = (float) ($m('cleaning_fee') ?: 0);
        $serviceFeeType = ($m('service_fee_type') ?: '') ?: 'fixed';
        $serviceFeeRaw = (float) ($m('service_fee') ?: 0);
        $petFee = $hasPets ? (float) ($m('pet_fee') ?: 0) : 0;
        $damageDeposit = (float) ($m('damage_deposit_amount') ?: 0);
        $requireDeposit = ($m('require_damage_deposit') ?: '') === '1';
        $weekendMultiplier = (float) ($m('weekend_multiplier') ?: 1.0) ?: 1.0;
        $baseGuests = (int) ($m('base_guests') ?: 2) ?: 2;
        $extraGuestFee = (float) ($m('extra_guest_fee') ?: 0);
        $includeCheckoutDay = ($m('include_checkout_day') ?: '') === '1';

        $currency = ! empty($m('currency'))
            ? strtoupper(strip_tags((string) $m('currency')))
            : $this->getCurrency($propertyId);

        $seasonalRates = $this->getSeasonalRates($property);
        $seasonalRatesParsed = [];
        foreach ($seasonalRates as $i => $rate) {
            if (! empty($rate['start_date']) && ! empty($rate['end_date']) && ! empty($rate['nightly_rate'])) {
                $startTs = strtotime($rate['start_date']);
                $endTs = strtotime($rate['end_date']);
                if ($startTs && $endTs) {
                    $seasonalRatesParsed[] = [
                        'start_ts' => $startTs,
                        'end_ts' => $endTs,
                        'rate' => (float) $rate['nightly_rate'],
                    ];
                }
            }
        }

        $weekendDays = $this->getWeekendDays($property);
        $weekdayPrices = $this->getWeekdayPrices($property);
        $lengthOfStayTiers = $this->getLengthOfStayTiers($property);
        $lengthOfStayRates = $this->getLengthOfStayRates($property);
        $bestTieredRate = $this->findBestTieredRate($nights, $lengthOfStayRates);

        $perNightBreakdown = [];
        $totalNightsCharged = 0;
        $weekendNightCount = 0;
        $effectiveNightlyRate = $bestTieredRate ?? $baseRate;

        $current = strtotime($checkIn);
        $checkOutTs = strtotime($checkOut);
        $endTs = $checkOutTs;

        while ($current < $endTs) {
            $dateStr = date('Y-m-d', $current);
            $isWeekend = $this->isWeekendDay($dateStr, $weekendDays);

            $rate = $baseRate;
            foreach ($seasonalRatesParsed as $sr) {
                if ($current >= $sr['start_ts'] && $current <= $sr['end_ts']) {
                    $rate = $sr['rate'];
                    break;
                }
            }

            $dayAbbr = strtolower(date('D', $current));
            if (! empty($weekdayPrices[$dayAbbr])) {
                $rate = (float) $weekdayPrices[$dayAbbr];
            } elseif ($isWeekend && $weekendMultiplier !== 1.0) {
                $rate = round($rate * $weekendMultiplier, 2);
                $weekendNightCount++;
            } elseif ($bestTieredRate !== null) {
                $rate = $bestTieredRate;
            }

            $perNightBreakdown[] = [
                'date' => $dateStr,
                'base_rate' => $rate,
                'is_weekend' => $isWeekend,
            ];

            $totalNightsCharged++;
            $current = strtotime('+1 day', $current);

            if (! $includeCheckoutDay && $current >= $checkOutTs) {
                break;
            }
        }

        $subtotal = round(array_sum(array_column($perNightBreakdown, 'base_rate')), 2);

        $discountPercent = $this->findLengthOfStayDiscount($totalNightsCharged, $lengthOfStayTiers);
        $discountAmount = $discountPercent > 0 ? round($subtotal * ($discountPercent / 100), 2) : 0;
        $afterDiscount = $subtotal - $discountAmount;

        $settings = SettingsService::getGroup('booking');
        $earlyBirdPercent = (int) ($settings['early_bird_discount_percent'] ?? 0);
        $earlyBirdDays = (int) ($settings['early_bird_days_before'] ?? 30);
        $lastMinutePercent = (int) ($settings['last_minute_discount_percent'] ?? 0);
        $lastMinuteDays = (int) ($settings['last_minute_days_within'] ?? 7);

        $promoDiscountPercent = 0;
        $promoDiscountLabel = '';

        if ($createdAt === null) {
            $createdAt = now()->toDateString();
        }
        $daysUntilCheckIn = 0;
        $checkInTs = strtotime($checkIn);
        $createdTs = strtotime($createdAt);

        if ($checkInTs && $createdTs) {
            $daysUntilCheckIn = (int) (($checkInTs - $createdTs) / 86400);
        }

        if ($earlyBirdPercent > 0 && $earlyBirdDays > 0 && $daysUntilCheckIn >= $earlyBirdDays) {
            $promoDiscountPercent = $earlyBirdPercent;
            $promoDiscountLabel = sprintf(__('Early bird (%d%% off)'), $earlyBirdPercent);
        } elseif ($lastMinutePercent > 0 && $lastMinuteDays > 0 && $daysUntilCheckIn <= $lastMinuteDays && $daysUntilCheckIn >= 0) {
            $promoDiscountPercent = $lastMinutePercent;
            $promoDiscountLabel = sprintf(__('Last minute (%d%% off)'), $lastMinutePercent);
        }

        $promoDiscountAmount = $promoDiscountPercent > 0 ? round($afterDiscount * ($promoDiscountPercent / 100), 2) : 0;
        $afterDiscount = $afterDiscount - $promoDiscountAmount;

        $nonrefundableDiscountPercent = 0;
        if ($isNonRefundable) {
            $nonrefundableDiscountPercent = (int) ($settings['nonrefundable_discount_percent'] ?? 10);
        }
        $nonrefundableDiscountAmount = $nonrefundableDiscountPercent > 0 ? round($afterDiscount * ($nonrefundableDiscountPercent / 100), 2) : 0;
        $afterDiscount = $afterDiscount - $nonrefundableDiscountAmount;

        $extraGuestCharge = 0;
        if ($extraGuestFee > 0 && $guests > $baseGuests) {
            $extraGuests = $guests - $baseGuests;
            $extraGuestCharge = round($extraGuestFee * $extraGuests * $totalNightsCharged, 2);
        }

        $serviceFee = $serviceFeeType === 'percentage' ? round($afterDiscount * ($serviceFeeRaw / 100), 2) : $serviceFeeRaw;
        $total = $afterDiscount + $cleaningFee + $serviceFee + $petFee + $extraGuestCharge;
        $depositCharged = $requireDeposit ? $damageDeposit : 0;

        $result = [
            'nightly_rates' => $perNightBreakdown,
            'nights' => $totalNightsCharged,
            'nights_calendar' => $nights,
            'weekend_nights' => $weekendNightCount,
            'base_nightly_rate' => $effectiveNightlyRate,
            'pricing_mode' => 'standard',
            'applied_length_of_stay_rate' => $bestTieredRate,
            'subtotal' => $subtotal,
            'length_of_stay_discount_percent' => $discountPercent,
            'length_of_stay_discount_amount' => $discountAmount,
            'promo_discount_percent' => $promoDiscountPercent,
            'promo_discount_amount' => $promoDiscountAmount,
            'promo_discount_label' => $promoDiscountLabel,
            'nonrefundable_discount_percent' => $nonrefundableDiscountPercent,
            'nonrefundable_discount_amount' => $nonrefundableDiscountAmount,
            'after_discount' => $afterDiscount,
            'base_guests' => $baseGuests,
            'extra_guest_fee' => $extraGuestFee,
            'extra_guest_charge' => $extraGuestCharge,
            'cleaning_fee' => $cleaningFee,
            'service_fee_type' => $serviceFeeType,
            'service_fee_rate' => $serviceFeeRaw,
            'service_fee' => $serviceFee,
            'pet_fee' => $petFee,
            'damage_deposit' => $depositCharged,
            'total' => round($total, 2),
            'currency' => $currency,
        ];

        $result['_version'] = $cacheVersion;
        Cache::put($cacheKey, $result, now()->addSeconds(self::PRICE_TTL));

        return $result;
    }

    public function calculateNights(string $checkIn, string $checkOut): int
    {
        $start = strtotime($checkIn);
        $end = strtotime($checkOut);
        if (! $start || ! $end || $end <= $start) {
            return 0;
        }

        return max(1, (int) ceil(($end - $start) / 86400));
    }

    public function getPerNightPrice(int $propertyId, string $date): float
    {
        $property = Property::findOrFail($propertyId);
        $baseRate = (float) ($property->metadata['nightly_rate'] ?? $property->price ?? 0);
        $seasonalRates = $this->getSeasonalRates($property);
        $weekendDays = $this->getWeekendDays($property);
        $weekendMultiplier = (float) ($property->metadata['weekend_multiplier'] ?? 1.0) ?: 1.0;
        $weekdayPrices = $this->getWeekdayPrices($property);

        $rate = $this->findSeasonalRate($date, $seasonalRates) ?: $baseRate;

        $dayAbbr = strtolower(date('D', strtotime($date)));
        if (! empty($weekdayPrices[$dayAbbr])) {
            $rate = (float) $weekdayPrices[$dayAbbr];
        } elseif ($this->isWeekendDay($date, $weekendDays) && $weekendMultiplier !== 1.0) {
            $rate = round($rate * $weekendMultiplier, 2);
        }

        return $rate;
    }

    private function getCacheVersion(int $propertyId): int
    {
        return (int) Cache::get('booking_cache_v_'.$propertyId, 1);
    }

    /**
     * @param  array<string, mixed>  $rates
     */
    private function findBestTieredRate(int $nights, array $rates): ?float
    {
        if (empty($rates)) {
            return null;
        }

        $sorted = $rates;
        usort($sorted, function ($a, $b) {
            return (int) ($a['min_nights'] ?? 0) - (int) ($b['min_nights'] ?? 0);
        });

        $best = null;
        foreach ($sorted as $tier) {
            $minNights = (int) ($tier['min_nights'] ?? 0);
            $rate = (float) ($tier['rate_per_night'] ?? 0);
            if ($minNights > 0 && $rate > 0 && $nights >= $minNights) {
                $best = $rate;
            }
        }

        return $best;
    }

    private function getLengthOfStayRates(int $propertyId): array
    {
        $property = Property::find($propertyId);

        return $property ? ($property->metadata['length_of_stay_rates'] ?? []) : [];
    }

    private function getSeasonalRates(Property $property): array
    {
        return $property->metadata['seasonal_rates'] ?? [];
    }

    private function getWeekdayPrices(Property $property): array
    {
        $raw = $property->metadata['weekday_prices'] ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    private function getWeekendDays(Property $property): array
    {
        $raw = $property->metadata['weekend_days'] ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : ['fri', 'sat'];
        }

        return is_array($raw) ? $raw : ['fri', 'sat'];
    }

    private function getLengthOfStayTiers(Property $property): array
    {
        return $property->metadata['length_of_stay_tiers'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $seasonalRates
     */
    private function findSeasonalRate(string $date, array $seasonalRates): ?float
    {
        $ts = strtotime($date);
        foreach ($seasonalRates as $rate) {
            if (empty($rate['start_date']) || empty($rate['end_date']) || empty($rate['nightly_rate'])) {
                continue;
            }
            $start = strtotime($rate['start_date']);
            $end = strtotime($rate['end_date']);
            if ($ts >= $start && $ts <= $end) {
                return (float) $rate['nightly_rate'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $weekendDays
     */
    private function isWeekendDay(string $date, array $weekendDays): bool
    {
        $dayOfWeek = strtolower(date('D', strtotime($date)));
        $map = [
            'mon' => 'mon', 'tue' => 'tue', 'wed' => 'wed',
            'thu' => 'thu', 'fri' => 'fri', 'sat' => 'sat', 'sun' => 'sun',
        ];
        $abbr = $map[$dayOfWeek] ?? '';

        return in_array($abbr, $weekendDays, true);
    }

    /**
     * @param  array<string, mixed>  $tiers
     */
    private function findLengthOfStayDiscount(int $nights, array $tiers): float
    {
        $best = 0.0;
        foreach ($tiers as $tier) {
            $min = (int) ($tier['min_nights'] ?? 0);
            $max = (int) ($tier['max_nights'] ?? 365);
            $percent = (float) ($tier['discount_percent'] ?? 0);
            if ($nights >= $min && $nights <= $max && $percent > $best) {
                $best = $percent;
            }
        }

        return $best;
    }

    private function getCurrency(int $propertyId): string
    {
        $property = Property::find($propertyId);
        if ($property && ! empty($property->currency)) {
            return $property->currency;
        }
        $settings = SettingsService::getGroup('booking');

        return $settings['default_currency'] ?? 'USD';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBreakdown(int $propertyId, string $currency = 'USD'): array
    {
        return [
            'nightly_rates' => [],
            'nights' => 0,
            'nights_calendar' => 0,
            'weekend_nights' => 0,
            'base_nightly_rate' => 0,
            'pricing_mode' => 'standard',
            'applied_length_of_stay_rate' => null,
            'subtotal' => 0,
            'length_of_stay_discount_percent' => 0,
            'length_of_stay_discount_amount' => 0,
            'promo_discount_percent' => 0,
            'promo_discount_amount' => 0,
            'promo_discount_label' => '',
            'nonrefundable_discount_percent' => 0,
            'nonrefundable_discount_amount' => 0,
            'after_discount' => 0,
            'base_guests' => 2,
            'extra_guest_fee' => 0,
            'extra_guest_charge' => 0,
            'cleaning_fee' => 0,
            'service_fee_type' => 'fixed',
            'service_fee_rate' => 0,
            'service_fee' => 0,
            'pet_fee' => 0,
            'damage_deposit' => 0,
            'total' => 0,
            'currency' => $currency,
        ];
    }
}
