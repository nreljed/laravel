<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class AttributeTerm extends Model
{
    protected $fillable = ['attribute_id', 'name', 'slug', 'sort_order', 'swatch_value'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (AttributeTerm $term) {
            if (! $term->slug) {
                $term->slug = Str::slug($term->name);
            }
        });
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute_term');
    }

    public function variations(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariation::class,
            'product_variation_attribute_term',
            'attribute_term_id',
            'product_variation_id'
        )->withPivot('attribute_id')->withTimestamps();
    }
}
