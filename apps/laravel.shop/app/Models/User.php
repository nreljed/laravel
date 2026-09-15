<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nreljed\Platform\Models\User as CoreUser;
use Nreljed\ShopModule\Models\Order;

/**
 * @method \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> users()
 */
class User extends CoreUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
