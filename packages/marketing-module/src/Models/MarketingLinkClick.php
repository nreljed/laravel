<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\MarketingModule\Database\Factories\MarketingLinkClickFactory;

class MarketingLinkClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_link_id',
        'clicked_at',
        'ip_hash',
        'user_agent',
        'referer',
        'country',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return MarketingLinkClickFactory::new();
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(MarketingLink::class, 'marketing_link_id');
    }
}
