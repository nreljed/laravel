<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\Platform\Models\User;

class OrderAction extends Model
{
    protected $fillable = [
        'order_id', 'action_type', 'old_value', 'new_value', 'user_id', 'notes',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
