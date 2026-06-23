<?php

namespace App\Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Services\BookingAvailabilityService;
use App\Modules\Booking\Services\BookingCacheService;
use App\Modules\Booking\Services\BookingPriceCalculator;
use App\Modules\Properties\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BookingAvailabilityController extends Controller
{
    public function __construct(
        protected BookingAvailabilityService $availabilityService,
        protected BookingCacheService $cacheService,
        protected BookingPriceCalculator $priceCalculator,
    ) {}

    public function check(int $propertyId, Request $request): JsonResponse
    {
        $property = Property::find($propertyId);
        if (! $property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $month = $request->input('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        [$year, $m] = explode('-', $month);
        $dates = $this->availabilityService->getMonthData($propertyId, (int) $year, (int) $m);

        $metadata = $property->metadata ?? [];
        $minNights = (int) ($metadata['booking_min_nights'] ?? 1) ?: 1;
        $maxNights = (int) ($metadata['booking_max_nights'] ?? 0) ?: 0;

        return response()->json([
            'property_id' => $propertyId,
            'month' => $month,
            'dates' => $dates,
            'min_nights' => $minNights,
            'max_nights' => $maxNights,
        ]);
    }

    public function block(Request $request): JsonResponse
    {
        $propertyId = (int) $request->input('property_id');
        $start = $request->input('start');
        $end = $request->input('end');
        $reason = $request->input('reason', '');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return response()->json(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
        }

        if ($end < $start) {
            return response()->json(['error' => 'End date must be after start date.'], 400);
        }

        $property = Property::find($propertyId);
        if (! $property || $property->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $metadata = $property->metadata ?? [];
        $blockedDates = $metadata['blocked_dates'] ?? [];
        if (! is_array($blockedDates)) {
            $blockedDates = [];
        }

        $blockedDates[] = [
            'start' => $start,
            'end' => $end,
            'reason' => $reason ?: '',
            'created_at' => now()->toDateTimeString(),
        ];

        $metadata['blocked_dates'] = $blockedDates;
        $property->update(['metadata' => $metadata]);

        $this->cacheService->bustPropertyCache($propertyId);

        return response()->json(['success' => true]);
    }

    public function unblock(int $id, Request $request): JsonResponse
    {
        $propertyId = (int) $request->input('property_id');

        $property = Property::find($propertyId);
        if (! $property || $property->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $metadata = $property->metadata ?? [];
        $blockedDates = $metadata['blocked_dates'] ?? [];
        if (! is_array($blockedDates)) {
            return response()->json(['error' => 'No blocked dates found'], 404);
        }

        if (! array_key_exists($id, $blockedDates)) {
            return response()->json(['error' => 'Blocked date entry not found'], 404);
        }

        unset($blockedDates[$id]);
        $blockedDates = array_values($blockedDates);
        $metadata['blocked_dates'] = $blockedDates;
        $property->update(['metadata' => $metadata]);

        $this->cacheService->bustPropertyCache($propertyId);

        return response()->json(['success' => true]);
    }

    public function clearOpcache(): JsonResponse
    {
        $cleared = 0;
        $files = [
            __FILE__,
            __DIR__.'/../Services/BookingAvailabilityService.php',
            __DIR__.'/../Services/BookingPriceCalculator.php',
            __DIR__.'/../Services/BookingCacheService.php',
        ];

        if (function_exists('opcache_reset')) {
            opcache_reset();
            $cleared = -1;
        } else {
            foreach ($files as $f) {
                if (file_exists($f)) {
                    clearstatcache(true, $f);
                    $cleared++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'method' => $cleared === -1 ? 'opcache_reset' : 'clearstatcache',
            'files_cleared' => $cleared === -1 ? 'all' : $cleared,
        ]);
    }

    public function cacheVersion(int $propertyId): JsonResponse
    {
        $cacheVersion = Cache::get('booking_cache_v_'.$propertyId, 1);

        return response()->json([
            'property_id' => $propertyId,
            'version' => (int) $cacheVersion,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function checkAvailabilityApi(Request $request): JsonResponse
    {
        $requestStart = microtime(true);

        $propertyId = (int) $request->input('property_id');
        $checkIn = $request->input('check_in');
        $checkOut = $request->input('check_out');
        $guests = (int) $request->input('guests', 1);
        $hasPets = $request->boolean('has_pets');
        $unitId = $request->input('unit_id');

        $availabilityCacheVersion = $this->cacheService->getCacheVersion($propertyId);
        $cacheKey = null;
        if ($checkIn && $checkOut) {
            $cacheKey = 'check_avail_'.$propertyId.'_'.$checkIn.'_'.$checkOut.'_g'.$guests.'_p'.($hasPets ? '1' : '0').'_u'.($unitId ?? 'none');
            $cached = Cache::get($cacheKey);
            if ($cached !== null && isset($cached['_version']) && $cached['_version'] === $availabilityCacheVersion) {
                $cached['_server_time_ms'] = round((microtime(true) - $requestStart) * 1000);
                $cached['_cached'] = true;

                return response()->json($cached);
            }
        }

        $timings = [];
        $t0 = microtime(true);

        $units = [];
        $available = true;
        $errorReason = null;

        if ($checkIn && $checkOut) {
            $property = Property::find($propertyId);
            $timings['get_property'] = round((microtime(true) - $t0) * 1000, 1);
            $t1 = microtime(true);

            $metadata = $property?->metadata ?? [];
            $prepDays = (int) ($metadata['preparation_days'] ?? 1);
            $adjustedCheckIn = $prepDays > 0
                ? now()->parse($checkIn)->subDays($prepDays)->toDateString()
                : $checkIn;

            $hasUnits = ! empty($metadata['units'] ?? []);
            if ($hasUnits) {
                $units = $metadata['units'] ?? [];
                $timings['units_check'] = round((microtime(true) - $t1) * 1000, 1);
                if ($unitId) {
                    $unitIds = array_column($units, 'id');
                    $available = in_array((string) $unitId, $unitIds, true);
                } else {
                    $available = ! empty($units);
                }
                if (! $available) {
                    $errorReason = __('No available units for the selected dates');
                }
            } else {
                $t1 = microtime(true);
                $available = $this->availabilityService->isAvailable($propertyId, $checkIn, $checkOut);
                $timings['is_available'] = round((microtime(true) - $t1) * 1000, 1);
                if (! $available) {
                    $errorReason = __('The selected dates are not available');
                }
            }
        } else {
            $metadata = [];
            $available = true;
        }

        $t1 = microtime(true);
        $property = $property ?? Property::find($propertyId);
        $price = $available ? $this->priceCalculator->calculateTotal($property, $checkIn, $checkOut, $guests, 0, $hasPets ? 1 : 0) : null;
        $timings['calculate_price'] = round((microtime(true) - $t1) * 1000, 1);

        $timings['total'] = round((microtime(true) - $requestStart) * 1000, 1);

        $response = [
            'available' => $available,
            'price' => $price,
            'property_id' => $propertyId,
            'units' => $units,
            'error_reason' => $errorReason,
            '_server_time_ms' => round($timings['total']),
            '_timing' => $timings,
        ];

        if ($checkIn && $checkOut) {
            $response['_version'] = $availabilityCacheVersion;
            Cache::put($cacheKey, $response, 30);
        }

        return response()->json($response);
    }
}
