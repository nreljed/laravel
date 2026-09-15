<?php

namespace Database\Factories;

use App\Models\User;
use Nreljed\Platform\Database\Factories\UserFactory as BaseUserFactory;

/**
 * @extends BaseUserFactory
 */
class UserFactory extends BaseUserFactory
{
    protected $model = User::class;
}
