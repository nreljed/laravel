<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class BookingStatusManager
{
    protected static array $transitions = [
        BookingStatus::Pending->value => [BookingStatus::Confirmed->value, BookingStatus::Cancelled->value],
        BookingStatus::Confirmed->value => [BookingStatus::CheckedIn->value, BookingStatus::Cancelled->value, BookingStatus::Pending->value, BookingStatus::NoShow->value],
        BookingStatus::CheckedIn->value => [BookingStatus::CheckedOut->value, BookingStatus::Cancelled->value, BookingStatus::Confirmed->value],
        BookingStatus::CheckedOut->value => [BookingStatus::Completed->value],
        BookingStatus::Cancelled->value => [],
        BookingStatus::NoShow->value => [BookingStatus::Cancelled->value],
        BookingStatus::Completed->value => [],
    ];

    public function canTransition(string $currentStatus, string $newStatus): bool
    {
        $allowed = self::$transitions[$currentStatus] ?? [];

        return in_array($newStatus, $allowed, true);
    }

    public function transition(Booking $booking, BookingStatus $newStatus, ?string $reason = null, string $cancelledBy = 'host'): Booking
    {
        $oldStatus = $booking->status;

        if (! $oldStatus->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$oldStatus->value} to {$newStatus->value}."
            );
        }

        DB::transaction(function () use ($booking, $newStatus, $reason, $cancelledBy) {
            $updates = ['status' => $newStatus->value];
            $meta = $booking->metadata ?? [];

            if ($reason) {
                $updates['cancellation_reason'] = $reason;
            }

            if ($newStatus === BookingStatus::Confirmed && ! $booking->confirmed_at) {
                $updates['confirmed_at'] = now();
            }

            if ($newStatus === BookingStatus::Cancelled && ! $booking->cancelled_at) {
                $updates['cancelled_at'] = now();

                $refund = $this->calculateRefundForBooking($booking, $cancelledBy);
                $meta['refund_percent'] = $refund['refund_percent'];
                $meta['refund_amount'] = $refund['refund_amount'];
                $meta['refund_policy_applied'] = $refund['policy_applied'];
                $meta['refund_reason'] = $refund['reason'];
            }

            if ($newStatus === BookingStatus::CheckedIn && ! $booking->checked_in_at) {
                $updates['checked_in_at'] = now();
            }

            if ($newStatus === BookingStatus::CheckedOut && ! $booking->checked_out_at) {
                $updates['checked_out_at'] = now();
                $meta['claim_window_expires_at'] = now()->addDays(30)->toISOString();
            }

            if ($meta !== ($booking->metadata ?? [])) {
                $updates['metadata'] = $meta;
            }

            $booking->update($updates);
        });

        $fresh = $booking->fresh();

        Event::dispatch('booking.status-transition', [$booking->id, $oldStatus->value, $newStatus->value]);

        if ($newStatus === BookingStatus::Cancelled) {
            BookingCacheService::bustPropertyCache($booking->property_id);
        }

        return $fresh;
    }

    public function calculateRefundForBooking(Booking $booking, string $cancelledBy = 'host'): array
    {
        $policy = $booking->metadata['cancellation_policy'] ?? 'flexible';
        $totalPrice = $booking->total_amount ?? 0;
        $checkInDate = $booking->start_date?->toDateString() ?? now()->toDateString();
        $totalNights = $booking->nights();
        $createdAt = $booking->created_at?->toDateTimeString() ?? now()->toDateTimeString();

        $meta = $booking->metadata ?? [];
        $fullRefundDays = (int) ($meta['full_refund_days'] ?? $meta['cancellation_full_refund_days'] ?? 1);
        $partialCutoffDays = (int) ($meta['partial_refund_cutoff_days'] ?? $meta['cancellation_partial_refund_cutoff_days'] ?? 0);
        $partialPercent = (int) ($meta['partial_refund_percent'] ?? $meta['cancellation_partial_refund_percent'] ?? 50);

        $isMidTrip = $booking->checked_in_at !== null;
        $nightsStayed = 0;
        if ($isMidTrip && $booking->checked_in_at) {
            $nightsStayed = max(1, (int) ceil($booking->checked_in_at->diffInDays(now())));
        }

        return $this->calculateRefund(
            $policy,
            $checkInDate,
            now()->toDateTimeString(),
            (float) $totalPrice,
            [
                'full_refund_days' => $fullRefundDays,
                'partial_refund_cutoff_days' => $partialCutoffDays,
                'partial_refund_percent' => $partialPercent,
                'total_nights' => $totalNights,
                'long_term_min_nights' => (int) ($meta['long_term_min_nights'] ?? 28),
                'long_term_nonrefundable_nights' => (int) ($meta['long_term_nonrefundable_nights'] ?? 30),
                'booked_at' => $createdAt,
                'is_mid_trip' => $isMidTrip,
                'nights_stayed' => $nightsStayed,
                'grace_period_hours' => (int) ($meta['grace_period_hours'] ?? 24),
                'grace_period_advance_days' => (int) ($meta['grace_period_advance_days'] ?? 7),
            ]
        );
    }

    public function calculateRefund(
        string $policy,
        string $checkInDate,
        ?string $cancelledAt = null,
        float $totalPrice = 0,
        array $params = []
    ): array {
        $now = $cancelledAt ? new \DateTime($cancelledAt) : new \DateTime('now');
        $checkInDt = new \DateTime($checkInDate);
        $hoursUntilCheckIn = ($checkInDt->getTimestamp() - $now->getTimestamp()) / 3600;

        $bookedAt = $params['booked_at'] ?? '';
        $graceHours = (int) ($params['grace_period_hours'] ?? 24);
        $advanceDays = (int) ($params['grace_period_advance_days'] ?? 7);

        if ($bookedAt && $this->isWithinGracePeriod($bookedAt, $checkInDate, $cancelledAt, $graceHours, $advanceDays)) {
            return [
                'refund_percent' => 100,
                'refund_amount' => round($totalPrice, 2),
                'policy_applied' => 'grace_period',
                'reason' => 'Cancelled within 24-hour grace period - full refund',
            ];
        }

        $fullRefundDays = (int) ($params['full_refund_days'] ?? 0);
        $partialCutoffDays = (int) ($params['partial_refund_cutoff_days'] ?? 0);
        $partialPercent = (int) ($params['partial_refund_percent'] ?? 50);
        $totalNights = (int) ($params['total_nights'] ?? 0);
        $longTermNonrefundableNights = (int) ($params['long_term_nonrefundable_nights'] ?? 30);
        $isMidTrip = (bool) ($params['is_mid_trip'] ?? false);
        $nightsStayed = (int) ($params['nights_stayed'] ?? 0);

        if ($isMidTrip && $nightsStayed > 0 && $totalNights > 0) {
            switch ($policy) {
                case 'flexible':
                    $refundableNights = max(0, $totalNights - $nightsStayed - 1);
                    $refundPercent = (int) round(($refundableNights / $totalNights) * 100);
                    $reason = "Mid-trip: guest refunded for {$refundableNights} unused nights (host keeps {$nightsStayed} stayed + 1)";
                    break;
                case 'moderate':
                    $refundableNights = max(0, $totalNights - $nightsStayed - 1);
                    $refundPercent = (int) round(($refundableNights * 0.5 / $totalNights) * 100);
                    $reason = "Mid-trip: guest refunded {$refundPercent}% of unused nights";
                    break;
                case 'long_term':
                    $noticeDays = (int) ($params['notice_days'] ?? 0);
                    if ($noticeDays >= 30) {
                        $refundableNights = max(0, $totalNights - $nightsStayed - 30);
                    } else {
                        $refundableNights = max(0, $totalNights - $nightsStayed - $longTermNonrefundableNights);
                    }
                    $refundPercent = (int) round(($refundableNights / $totalNights) * 100);
                    $reason = "Mid-trip (long-term): guest refunded {$refundableNights} of {$totalNights} nights";
                    break;
                default:
                    $refundPercent = 0;
                    $reason = 'No mid-trip refund under this policy';
                    break;
            }

            return [
                'refund_percent' => $refundPercent,
                'refund_amount' => round($totalPrice * $refundPercent / 100, 2),
                'policy_applied' => $policy,
                'reason' => $reason,
            ];
        }

        switch ($policy) {
            case 'non_refundable':
                $refundPercent = 0;
                $reason = 'Non-refundable policy - no refund';
                break;

            case 'long_term':
                if ($totalNights < ($params['long_term_min_nights'] ?? 28)) {
                    $refundPercent = 100;
                    $reason = 'Long-term policy - booking too short for terms, full refund';
                } elseif ($hoursUntilCheckIn >= $fullRefundDays * 24) {
                    $refundableNights = max(0, $totalNights - $longTermNonrefundableNights);
                    $refundPercent = (int) round(($refundableNights / max(1, $totalNights)) * 100);
                    $reason = "Long-term policy - {$refundableNights} of {$totalNights} nights refundable (first {$longTermNonrefundableNights} kept)";
                } else {
                    $refundableNights = max(0, $totalNights - $longTermNonrefundableNights);
                    $refundPercent = (int) round(($refundableNights / max(1, $totalNights)) * 100);
                    $reason = "Long-term policy - cancelled within window, {$refundableNights} of {$totalNights} nights refundable";
                }
                break;

            case 'firm':
                $firmFullRefundDays = $fullRefundDays ?: 30;
                $firmPartialCutoffDays = $partialCutoffDays ?: 7;
                $firmPartialPercent = $partialPercent ?: 50;
                if ($hoursUntilCheckIn >= $firmFullRefundDays * 24) {
                    $refundPercent = 100;
                    $reason = "Firm policy - full refund (cancelled {$firmFullRefundDays}+ days before)";
                } elseif ($hoursUntilCheckIn >= $firmPartialCutoffDays * 24) {
                    $refundPercent = $firmPartialPercent;
                    $reason = "Firm policy - {$refundPercent}% refund (cancelled {$firmPartialCutoffDays}-{$firmFullRefundDays} days before)";
                } else {
                    $refundPercent = 0;
                    $reason = "Firm policy - no refund (cancelled within {$firmPartialCutoffDays} days)";
                }
                break;

            case 'limited':
                $limitedFullRefundDays = $fullRefundDays ?: 14;
                $limitedPartialCutoffDays = $partialCutoffDays ?: 7;
                $limitedPartialPercent = $partialPercent ?: 50;
                if ($hoursUntilCheckIn >= $limitedFullRefundDays * 24) {
                    $refundPercent = 100;
                    $reason = "Limited policy - full refund (cancelled {$limitedFullRefundDays}+ days before)";
                } elseif ($hoursUntilCheckIn >= $limitedPartialCutoffDays * 24) {
                    $refundPercent = $limitedPartialPercent;
                    $reason = "Limited policy - {$refundPercent}% refund (cancelled {$limitedPartialCutoffDays}-{$limitedFullRefundDays} days before)";
                } else {
                    $refundPercent = 0;
                    $reason = "Limited policy - no refund (cancelled within {$limitedPartialCutoffDays} days)";
                }
                break;

            case 'strict':
                $strictFullRefundDays = $fullRefundDays ?: 7;
                if ($hoursUntilCheckIn >= $strictFullRefundDays * 24) {
                    $refundPercent = $partialPercent ?: 50;
                    $reason = "Strict policy - {$refundPercent}% refund (cancelled {$strictFullRefundDays}+ days before)";
                } else {
                    $refundPercent = 0;
                    $reason = "Strict policy - no refund (cancelled within {$strictFullRefundDays} days)";
                }
                break;

            case 'moderate':
                $moderateFullRefundDays = $fullRefundDays ?: 5;
                $moderatePartialCutoffDays = $partialCutoffDays ?: 1;
                $moderatePartialPercent = $partialPercent ?: 50;
                if ($hoursUntilCheckIn >= $moderateFullRefundDays * 24) {
                    $refundPercent = 100;
                    $reason = "Moderate policy - full refund (cancelled {$moderateFullRefundDays}+ days before)";
                } elseif ($hoursUntilCheckIn >= $moderatePartialCutoffDays * 24) {
                    $refundPercent = $moderatePartialPercent;
                    $reason = "Moderate policy - {$refundPercent}% refund (cancelled {$moderatePartialCutoffDays}-{$moderateFullRefundDays} days before)";
                } else {
                    $refundPercent = 0;
                    $reason = "Moderate policy - no refund (cancelled within {$moderatePartialCutoffDays} days)";
                }
                break;

            case 'flexible':
            default:
                $flexFullRefundDays = $fullRefundDays ?: 1;
                $flexPartialCutoffDays = $partialCutoffDays ?: 0;
                $flexPartialPercent = $partialCutoffDays > 0 ? ($partialPercent ?: 50) : 50;
                if ($hoursUntilCheckIn >= $flexFullRefundDays * 24) {
                    $refundPercent = 100;
                    $reason = "Flexible policy - full refund (cancelled {$flexFullRefundDays}+ days before)";
                } elseif ($flexPartialCutoffDays > 0 && $hoursUntilCheckIn >= $flexPartialCutoffDays * 24) {
                    $refundPercent = $flexPartialPercent;
                    $reason = "Flexible policy - {$refundPercent}% refund (cancelled {$flexPartialCutoffDays}-{$flexFullRefundDays} days before)";
                } else {
                    $refundPercent = $flexPartialPercent;
                    $reason = "Flexible policy - {$refundPercent}% refund (cancelled within window)";
                }
                break;
        }

        return [
            'refund_percent' => $refundPercent,
            'refund_amount' => round($totalPrice * $refundPercent / 100, 2),
            'policy_applied' => $policy,
            'reason' => $reason,
        ];
    }

    public function isWithinGracePeriod(
        string $bookedAt,
        string $checkInDate,
        ?string $cancelledAt = null,
        int $graceHours = 24,
        int $advanceDays = 7
    ): bool {
        $now = $cancelledAt ? new \DateTime($cancelledAt) : new \DateTime('now');
        $bookedDt = new \DateTime($bookedAt);
        $checkInDt = new \DateTime($checkInDate);

        $hoursSinceBooking = ($now->getTimestamp() - $bookedDt->getTimestamp()) / 3600;
        $daysUntilCheckIn = ($checkInDt->getTimestamp() - $now->getTimestamp()) / 86400;

        return $hoursSinceBooking < $graceHours && $daysUntilCheckIn >= $advanceDays;
    }

    public function processAutoTransitions(): array
    {
        $results = ['checked_out' => 0, 'cancelled_no_show' => 0, 'deposit_released' => 0];

        $toCheckOut = Booking::where('status', BookingStatus::CheckedIn->value)
            ->where('end_date', '<=', now()->subHours(24)->toDateString())
            ->get();

        foreach ($toCheckOut as $booking) {
            try {
                $this->transition($booking, BookingStatus::CheckedOut, 'Auto checkout after grace period');
                $results['checked_out']++;
            } catch (\Exception $e) {
                report($e);
            }
        }

        $noShows = Booking::whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->where('start_date', '<=', now()->subDays(2)->toDateString())
            ->get();

        foreach ($noShows as $booking) {
            try {
                $this->transition($booking, BookingStatus::NoShow, 'Auto no-show after 2 days');
                $results['cancelled_no_show']++;
            } catch (\Exception $e) {
                report($e);
            }
        }

        $now = now();
        $expiredDeposits = Booking::where('status', BookingStatus::CheckedOut->value)
            ->get()
            ->filter(function (Booking $booking) use ($now) {
                $meta = $booking->metadata ?? [];
                $claimExpiry = $meta['claim_window_expires_at'] ?? null;
                if (! $claimExpiry) {
                    return false;
                }

                return $claimExpiry <= $now->toISOString() && empty($meta['deposit_released_at']);
            });

        foreach ($expiredDeposits as $booking) {
            $meta = $booking->metadata ?? [];
            $meta['deposit_status'] = 'refunded';
            $meta['deposit_refunded_at'] = $now->toISOString();
            $meta['deposit_released_at'] = $now->toISOString();
            $booking->update(['metadata' => $meta]);
            $results['deposit_released']++;
        }

        return $results;
    }
}
