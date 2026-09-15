<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code', 'name', 'symbol', 'exchange_rate',
        'decimal_places', 'is_default', 'is_active',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'decimal_places' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
