<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ShippingZone extends Model
{
    protected $fillable = [
        'name', 'countries', 'regions', 'is_active',
    ];

    protected $casts = [
        'countries' => 'array',
        'regions' => 'array',
        'is_active' => 'boolean',
    ];

    public function methods(): BelongsToMany
    {
        return $this->belongsToMany(ShippingMethod::class, 'shipping_zone_method')
            ->withPivot('override_price')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
