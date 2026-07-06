<?php

namespace App\Modules\Properties\Models;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Contracts\Bookable;
use App\Services\SettingsService;
use Carbon\Carbon;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Property extends Model implements Bookable
{
    use HasFactory;

    protected $table = 'properties';

    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'address', 'city', 'state',
        'zip', 'country', 'latitude', 'longitude',
        'price', 'currency', 'surface', 'surface_unit',
        'status', 'published_at', 'featured', 'metadata', 'booking_settings',
        'video_url',
    ];

    protected static function newFactory(): Factory
    {
        return PropertyFactory::new();
    }

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'price' => 'decimal:2',
            'surface' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'metadata' => 'array',
            'booking_settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Property $property) {
            if (! $property->slug) {
                $property->slug = Str::slug($property->name).'-'.Str::random(6);
            }
        });
    }

    public function getFormattingOptions(): array
    {
        $defaults = SettingsService::getFormattingOptions();

        return array_merge($defaults, array_filter([
            'currency' => $this->currency,
            'unit' => $this->surface_unit,
        ]));
    }

    public function getCoordinatesAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude];
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function statuses(): BelongsToMany
    {
        return $this->belongsToMany(PropertyStatus::class, 'property_property_status');
    }

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(PropertyType::class, 'property_property_type');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(PropertyFeature::class, 'property_property_feature');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order');
    }

    public function featuredImage(): HasOne
    {
        return $this->hasOne(PropertyImage::class)->where('is_featured', true);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'future')->where('published_at', '<=', now());
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return (float) ($this->price ?? 0);
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function getMaxGuests(): int
    {
        return (int) ($this->metadata['max_guests'] ?? config('booking.defaults.max_guests', 4));
    }

    public function isAvailable(Carbon $from, Carbon $to, ?int $excludeBookingId = null): bool
    {
        return $this->bookings()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('check_in', [$from, $to])
                    ->orWhereBetween('check_out', [$from, $to])
                    ->orWhere(function ($q) use ($from, $to) {
                        $q->where('check_in', '<=', $from)
                            ->where('check_out', '>=', $to);
                    });
            })
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->doesntExist();
    }

    public function getAvailabilityData(Carbon $from, Carbon $to): array
    {
        $bookings = $this->bookings()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('check_in', [$from, $to])
                    ->orWhereBetween('check_out', [$from, $to])
                    ->orWhere(function ($q) use ($from, $to) {
                        $q->where('check_in', '<=', $from)
                            ->where('check_out', '>=', $to);
                    });
            })
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->get(['check_in', 'check_out', 'status']);

        $blocked = $this->metadata['booking_blocked_dates'] ?? [];

        return [
            'bookings' => $bookings,
            'blocked_dates' => $blocked,
        ];
    }

    public function getImages(): Collection
    {
        return $this->images;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function getProviderSlug(): string
    {
        return 'properties';
    }

    public function getCurrency(): string
    {
        return strtoupper($this->currency ?? 'USD');
    }

    public function getCleaningFee(): float
    {
        return (float) ($this->cleaning_fee ?? 0);
    }

    public function getDamageDeposit(): float
    {
        return (float) ($this->damage_deposit ?? 0);
    }

    public function getOwnerId(): ?int
    {
        return $this->user_id;
    }

    public function getFeaturedImageUrl(): ?string
    {
        return $this->featuredImage?->url;
    }
}
