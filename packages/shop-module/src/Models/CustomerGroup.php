<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends Model
{
    protected $fillable = [
        'code', 'label', 'allows_business_pricing',
    ];

    protected $casts = [
        'allows_business_pricing' => 'boolean',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'customer_group_id');
    }
}