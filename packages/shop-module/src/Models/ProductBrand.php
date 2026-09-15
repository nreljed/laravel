<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ProductBrand extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'image', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (ProductBrand $brand) {
            if (! $brand->slug) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product_brand');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
