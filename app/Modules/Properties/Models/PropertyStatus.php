<?php

namespace App\Modules\Properties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class PropertyStatus extends Model
{
    protected $table = 'property_statuses';

    protected $fillable = [
        'name', 'slug', 'description', 'sort_order', 'is_active',
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
        static::creating(function (self $status) {
            if (!$status->slug) {
                $status->slug = Str::slug($status->name);
            }
        });
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_property_status');
    }
}
