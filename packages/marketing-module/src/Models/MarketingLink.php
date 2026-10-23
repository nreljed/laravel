<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nreljed\MarketingModule\Database\Factories\MarketingLinkFactory;

class MarketingLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'social_post_id',
        'social_post_target_id',
        'destination_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'short_code',
        'clicks_count',
        'last_clicked_at',
        'is_active',
    ];

    protected $casts = [
        'clicks_count' => 'integer',
        'last_clicked_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): Factory
    {
        return MarketingLinkFactory::new();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(SocialPostTarget::class, 'social_post_target_id');
    }

    /**
     * @return HasMany<MarketingLinkClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(MarketingLinkClick::class);
    }
}
