<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ProductTag extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (ProductTag $tag) {
            if (! $tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tag_product');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
