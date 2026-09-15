<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariationAttributeTerm extends Model
{
    protected $table = 'product_variation_attribute_term';

    protected $fillable = [
        'product_variation_id', 'attribute_id', 'attribute_term_id',
    ];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AttributeTerm::class, 'attribute_term_id');
    }
}
