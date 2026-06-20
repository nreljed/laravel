<?php

namespace App\Modules\Booking\Services;

use App\Modules\Properties\Models\Property;

class BookingPriceCalculator
{
    public function calculateTotal(
        Property $property,
        string $startDate,
        string $endDate,
        int $adults = 1,
        int $children = 0,
        int $pets = 0,
    ): array {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        $nights = $start->diffInDays($end);

        $nightlyRate = $property->price ?? 0;
        $currency = $property->currency ?? 'USD';

        $subtotal = $nightlyRate * $nights;
        $cleaningFee = $property->cleaning_fee ?? 0;
        $serviceFee = round($subtotal * 0.05, 2);
        $damageDeposit = $property->damage_deposit ?? 0;
        $totalAmount = $subtotal + $cleaningFee + $serviceFee + $damageDeposit;

        return [
            'nights' => $nights,
            'nightly_rate' => $nightlyRate,
            'currency' => $currency,
            'subtotal' => $subtotal,
            'cleaning_fee' => $cleaningFee,
            'service_fee' => $serviceFee,
            'damage_deposit' => $damageDeposit,
            'total_amount' => $totalAmount,
            'adults' => $adults,
            'children' => $children,
            'pets' => $pets,
        ];
    }
}
