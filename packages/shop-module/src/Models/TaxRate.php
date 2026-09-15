<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = [
        'name', 'rate', 'type', 'country', 'state',
        'is_active', 'is_default',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCountry($query, ?string $country)
    {
        if ($country) {
            return $query->where('country', $country);
        }

        return $query->whereNull('country');
    }
}
