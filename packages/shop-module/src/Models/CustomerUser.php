<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CustomerUser extends Pivot
{
    protected $table = 'customer_user';
}