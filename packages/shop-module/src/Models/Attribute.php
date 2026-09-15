<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Attribute extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order', 'is_active', 'display_type'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'display_type' => 'string',
    ];

    public const DISPLAY_TYPES = ['text', 'color', 'image', 'button'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Attribute $attribute) {
            if (! $attribute->slug) {
                $attribute->slug = Str::slug($attribute->name);
            }
        });
    }

    public function terms(): HasMany
    {
        return $this->hasMany(AttributeTerm::class)->orderBy('sort_order')->orderBy('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
