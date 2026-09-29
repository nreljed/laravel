<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A curated shopper-facing link between two products (PR 11B).
 *
 * Merchant-declared upsells ("buy the bigger one") and cross-sells ("people
 * who bought this also bought X") are the two trade meanings of "related",
 * each kept as a separate `type` on the pivot. The storefront's related rail
 * serves these in `sort_order` before it pads the remainder out of the
 * category, so a merchant's merchandising is never drowned by random fill.
 */
class ProductRelation extends Model
{
    public const TYPE_CROSS_SELL = 'cross_sell';

    public const TYPE_UP_SELL = 'up_sell';

    /** @var list<string> */
    public const TYPES = [self::TYPE_CROSS_SELL, self::TYPE_UP_SELL];

    protected $fillable = ['product_id', 'related_product_id', 'type', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}