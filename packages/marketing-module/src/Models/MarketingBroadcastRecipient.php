<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingBroadcastRecipient extends Model
{
    protected $table = 'marketing_broadcast_recipients';

    protected $fillable = [
        'marketing_broadcast_id',
        'contact_id',
        'status',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(MarketingBroadcast::class, 'marketing_broadcast_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
