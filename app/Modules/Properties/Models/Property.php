<?php

namespace App\Modules\Properties\Models;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Services\SettingsService;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory;

    protected $table = 'properties';

    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'address', 'city', 'state',
        'zip', 'country', 'latitude', 'longitude',
        'price', 'currency', 'surface', 'surface_unit',
        'status', 'featured', 'images', 'amenities', 'metadata',
        'video_url', 'virtual_tour_url',
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
            'images' => 'array',
            'amenities' => 'array',
            'metadata' => 'array',
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

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PropertyCategory::class, 'property_property_category');
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
}
