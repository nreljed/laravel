<?php

namespace App\Modules\Properties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class PropertyFeature extends Model
{
    protected $table = 'property_features';

    protected $fillable = [
        'name', 'slug', 'icon', 'group', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $feature) {
            if (!$feature->slug) {
                $feature->slug = Str::slug($feature->name);
            }
        });
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_property_feature');
    }
}
