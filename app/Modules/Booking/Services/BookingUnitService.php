<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Models\Booking;
use App\Modules\Properties\Models\Property;
use Illuminate\Support\Facades\Log;

class BookingUnitService
{
    /** @var array<int, array<string, mixed>> */
    private static array $unitConfigCache = [];

    /**
     * @return array<string, mixed>
     */
    public function getUnitConfig(int $propertyId): array
    {
        if (! isset(self::$unitConfigCache[$propertyId])) {
            $property = Property::find($propertyId);
            self::$unitConfigCache[$propertyId] = $property ? ($property->metadata['unit_config'] ?? []) : [];
        }

        return self::$unitConfigCache[$propertyId];
    }

    public static function clearUnitConfigCache(int $propertyId): void
    {
        unset(self::$unitConfigCache[$propertyId]);
    }

    public function hasUnits(int $propertyId): bool
    {
        return ! empty($this->getUnitConfig($propertyId));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUnitById(int $propertyId, string $unitId): ?array
    {
        $units = $this->getUnitConfig($propertyId);
        foreach ($units as $i => $unit) {
            $id = $unit['id'] ?? 'unit_'.$i;
            if ($id === $unitId) {
                $unit['index'] = $i;
                $unit['id'] = $id;

                return $unit;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    public function getUnitEffectiveRate(int $propertyId, array $unit): float
    {
        if (! empty($unit['nightly_rate'])) {
            return (float) $unit['nightly_rate'];
        }
        $property = Property::find($propertyId);

        return (float) ($property->metadata['nightly_rate'] ?? $property->price ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBookedUnitCount(int $propertyId, ?string $unitType, string $checkIn, string $checkOut): array
    {
        $statusValues = array_map(fn ($s) => $s->value, BookingStatus::activeStatuses());

        $bookings = Booking::where('property_id', $propertyId)
            ->whereIn('status', $statusValues)
            ->where('start_date', '<', $checkOut)
            ->where('end_date', '>', $checkIn)
            ->get();

        Log::debug("getBookedUnitCount(property={$propertyId}): found {$bookings->count()} overlapping booking rows");

        $countByType = [];
        foreach ($bookings as $row) {
            $meta = $row->metadata ?? [];
            $unitTypeVal = $meta['unit_type'] ?? '';
            if ($unitTypeVal) {
                $countByType[$unitTypeVal] = ($countByType[$unitTypeVal] ?? 0) + 1;
            }
        }

        return $countByType;
    }

    public function isUnitAvailable(int $propertyId, string $unitId, string $checkIn, string $checkOut): bool
    {
        $unit = $this->getUnitById($propertyId, $unitId);
        if (! $unit) {
            return false;
        }

        $quantity = (int) ($unit['quantity'] ?? 1);
        $unitType = $unit['type'] ?? 'room';
        $unitName = $unit['name'] ?? '';

        $bookedByType = $this->getBookedUnitCount($propertyId, $unitType, $checkIn, $checkOut);

        $typeKey = $unitType.':'.$unitName;
        $bookedCount = ($bookedByType[$typeKey] ?? 0);

        return $bookedCount < $quantity;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableUnits(int $propertyId, string $checkIn, string $checkOut): array
    {
        $units = $this->getUnitConfig($propertyId);
        $available = [];

        $bookedByType = $this->getBookedUnitCount($propertyId, null, $checkIn, $checkOut);

        foreach ($units as $i => $unit) {
            $unitId = $unit['id'] ?? 'unit_'.$i;
            $quantity = (int) ($unit['quantity'] ?? 1);
            $unitType = $unit['type'] ?? 'room';
            $unitName = $unit['name'] ?? '';
            $typeKey = $unitType.':'.$unitName;
            $bookedCount = $bookedByType[$typeKey] ?? 0;

            if ($bookedCount < $quantity) {
                $unit['id'] = $unitId;
                $unit['index'] = $i;
                $unit['remaining'] = $quantity - $bookedCount;
                $unit['effective_rate'] = $this->getUnitEffectiveRate($propertyId, $unit);
                $available[] = $unit;
            }
        }

        return $available;
    }
}
