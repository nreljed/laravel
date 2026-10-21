<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Nreljed\MarketingModule\Database\Factories\SegmentFactory;

/**
 * A saved filter over the contact table.
 *
 * The condition tree is stored as JSON in the shape the condition evaluator
 * reads: a list of OR groups, each holding a list of AND conditions.
 */
class Segment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'conditions',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): Factory
    {
        return SegmentFactory::new();
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'segment_memberships');
    }
}
