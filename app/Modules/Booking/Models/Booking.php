<?php

namespace App\Modules\Booking\Models;

use App\Models\User;
use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Properties\Models\Property;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $table = 'bookings';

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
        return \Database\Factories\BookingFactory::new();
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
