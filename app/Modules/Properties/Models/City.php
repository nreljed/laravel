<?php

namespace App\Modules\Properties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class City extends Model
{
    protected $fillable = [
        'name', 'slug', 'image', 'governorate', 'governorate_slug', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $city) {
            if (! $city->slug) {
                $city->slug = Str::slug($city->name);
            }
        });
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'city_id');
    }
}
