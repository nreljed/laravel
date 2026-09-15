<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    protected $fillable = [
        'name', 'description', 'price', 'free_above',
        'estimated_days_min', 'estimated_days_max',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'free_above' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
