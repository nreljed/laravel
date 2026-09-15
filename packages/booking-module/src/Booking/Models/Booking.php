<?php

namespace Nreljed\BookingModule\Booking\Models;

use Carbon\Carbon;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Event;
use Nreljed\BookingModule\Booking\Enums\BookingStatus;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\Platform\Models\User;

class Booking extends Model
{
    use HasFactory;

    protected $table = 'bookings';

    protected static function booted(): void
    {
        static::created(function (Booking $booking) {
            Event::dispatch('booking.created', [$booking->id]);
        });

        static::saved(function (Booking $booking) {
            Event::dispatch('booking.saved', [$booking->id]);
        });

        static::deleted(function (Booking $booking) {
            Event::dispatch('booking.deleted', [$booking->id]);
        });
    }

    protected $fillable = [
        'property_id', 'user_id', 'start_date', 'end_date', 'status',
        'total_amount', 'currency', 'guest_count', 'notes', 'metadata',
        'guest_name', 'guest_email', 'guest_phone', 'check_in', 'check_out',
        'nightly_rate', 'cleaning_fee', 'service_fee', 'damage_deposit',
        'adults', 'children', 'pets', 'source', 'cancellation_reason',
        'confirmed_at', 'cancelled_at', 'checked_in_at', 'checked_out_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'archived_at' => 'datetime',
            'status' => BookingStatus::class,
            'total_amount' => 'decimal:2',
            'nightly_rate' => 'decimal:2',
            'cleaning_fee' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'damage_deposit' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): Factory
    {
        return BookingFactory::new();
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nights(): int
    {
        return Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date));
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(fn ($s) => $s->value, BookingStatus::activeStatuses()));
    }

    public function scopeDateRange(Builder $query, string $start, string $end): Builder
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
                ->orWhereBetween('end_date', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->where('start_date', '<=', $start)
                        ->where('end_date', '>=', $end);
                });
        });
    }

    public function scopeOverlapping(Builder $query, int $propertyId, string $start, string $end, ?int $excludeId = null): Builder
    {
        $query->where('property_id', $propertyId)
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query;
    }

    public function scopeProperty(Builder $query, int $propertyId): Builder
    {
        return $query->where('property_id', $propertyId);
    }
}
