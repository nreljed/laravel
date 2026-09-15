<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Nreljed\Platform\Models\User as BaseUser;

class User extends BaseUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
