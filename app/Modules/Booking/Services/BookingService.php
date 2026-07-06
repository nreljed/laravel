<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\Booking;
use App\Services\FormattingService;
use App\Services\SettingsService;
use Illuminate\Pagination\LengthAwarePaginator;

class BookingService
{
    public function search(array $filters = []): LengthAwarePaginator
    {
        $query = Booking::with(['property', 'user']);

        if (! empty($filters['status'])) {
            $query->status($filters['status']);
        }

        if (! empty($filters['property_id'])) {
            $query->property((int) $filters['property_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->dateRange($filters['date_from'], $filters['date_to']);
        } elseif (! empty($filters['date_from'])) {
            $query->where('start_date', '>=', $filters['date_from']);
        } elseif (! empty($filters['date_to'])) {
            $query->where('end_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortDir = $filters['dir'] ?? 'desc';
        $perPage = (int) ($filters['per_page'] ?? 12);

        $query->orderBy($sortField, $sortDir);

        $bookings = $query->paginate($perPage);

        $bookings->getCollection()->transform(function (Booking $booking) {
            $data = $booking->toArray();
            $data['nights'] = $booking->nights();

            if ($booking->total_amount) {
                $options = array_merge(
                    SettingsService::getFormattingOptions(),
                    ['currency' => $booking->currency ?? 'USD'],
                );
                $data['total_amount_formatted'] = app(FormattingService::class)->formatPrice($booking->total_amount, $options);
            }

            return $data;
        });

        return $bookings;
    }

    public function getStats(): array
    {
        return [
            'total' => Booking::count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'checked_in' => Booking::where('status', 'checked_in')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
            'total_revenue' => Booking::whereIn('status', ['confirmed', 'checked_in', 'checked_out', 'completed'])->sum('total_amount'),
            'upcoming' => Booking::whereIn('status', ['pending', 'confirmed'])
                ->where('start_date', '>=', now()->toDateString())
                ->count(),
        ];
    }

    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    public function update(Booking $booking, array $data): Booking
    {
        $booking->update($data);

        return $booking->fresh();
    }
}
