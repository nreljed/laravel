<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class ProductVariation extends Model
{
    protected $attributes = [
        'visible' => true,
    ];

    protected $fillable = [
        'product_id', 'sku', 'barcode', 'price', 'compare_price', 'sale_price',
        'sale_from', 'sale_to', 'active_price', 'stock_quantity', 'weight', 'length', 'width', 'height',
        'is_active', 'visible', 'image_url', 'images', 'description', 'attributes', 'sort_order',
        'manage_stock', 'backorders', 'stock_status',
    ];

    protected $appends = [
        'is_visible',
        'on_sale',
        'purchasable',
        'backorders_allowed',
        'backordered',
    ];

    protected $casts = [
        'price' => 'float',
        'compare_price' => 'float',
        'sale_price' => 'float',
        'active_price' => 'float',
        'sale_from' => 'datetime',
        'sale_to' => 'datetime',
        'stock_quantity' => 'integer',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_active' => 'boolean',
        'visible' => 'boolean',
        'attributes' => 'array',
        'images' => 'array',
        'sort_order' => 'integer',
        'manage_stock' => 'string',
        'stock_status' => 'string',
        'backorders' => 'string',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (ProductVariation $variation) {
            $dirty = $variation->getDirty();
            $manageStockExplicitlySet = array_key_exists('manage_stock', $dirty);

            if (! $variation->exists && ! $manageStockExplicitlySet) {
                $variation->loadMissing('product');
                if ($variation->product && $variation->product->track_stock) {
                    $variation->manage_stock = 'parent';
                }
            }

            if ($variation->managesOwnStock() && ! $variation->isDirty('stock_status')) {
                if ($variation->stock_quantity > 0) {
                    $variation->stock_status = 'instock';
                } elseif ($variation->backorders !== 'no') {
                    $variation->stock_status = 'onbackorder';
                } else {
                    $variation->stock_status = 'outofstock';
                }
            }

            $variation->calculateActivePrice();
        });

        static::saved(function (ProductVariation $variation) {
            if ($variation->product) {
                $variation->product->syncPriceRange();
            }
        });

        static::deleted(function (ProductVariation $variation) {
            if ($variation->product) {
                $variation->product->syncPriceRange();
            }
        });
    }

    public function calculateActivePrice(): void
    {
        $price = $this->price;
        $salePrice = $this->sale_price;

        if ($salePrice !== null && $salePrice !== '') {
            $saleFrom = $this->sale_from;
            $saleTo = $this->sale_to;
            $now = now();
            $inRange = true;

            if ($saleFrom && $saleTo) {
                $inRange = $now->between($saleFrom, $saleTo);
            } elseif ($saleFrom) {
                $inRange = $now->gte($saleFrom);
            } elseif ($saleTo) {
                $inRange = $now->lte($saleTo);
            }

            if ($inRange) {
                $this->active_price = (float) $salePrice;

                return;
            }
        }

        $this->active_price = $price !== null && $price !== '' ? (float) $price : null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeTerms(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeTerm::class,
            'product_variation_attribute_term',
            'product_variation_id',
            'attribute_term_id'
        )->withPivot('attribute_id')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isVisible(): bool
    {
        return $this->getAttribute('visible') && $this->active_price !== null;
    }

    public function getIsVisibleAttribute(): bool
    {
        return $this->isVisible();
    }

    public function getOnSaleAttribute(): bool
    {
        $salePrice = $this->sale_price;

        if ($salePrice === null || $salePrice === '') {
            return false;
        }

        $saleFrom = $this->sale_from;
        $saleTo = $this->sale_to;
        $now = now();

        if ($saleFrom && $saleTo) {
            return $now->between($saleFrom, $saleTo);
        }

        if ($saleFrom) {
            return $now->gte($saleFrom);
        }

        if ($saleTo) {
            return $now->lte($saleTo);
        }

        return true;
    }

    public function getPurchasableAttribute(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->active_price === null) {
            return false;
        }

        if ($this->usesParentStock()) {
            return $this->product ? $this->product->isInStock() : true;
        }

        if (! $this->managesOwnStock()) {
            return true;
        }

        return $this->stock_quantity > 0 || $this->backorders !== 'no';
    }

    public function getBackordersAllowedAttribute(): bool
    {
        return $this->backorders !== 'no';
    }

    public function getBackorderedAttribute(): bool
    {
        return $this->backorders_allowed && $this->managesOwnStock() && $this->stock_quantity <= 0;
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNotNull('active_price');
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where(function ($sub) {
                $sub->where('manage_stock', 'parent');
            })->orWhere(function ($sub) {
                $sub->where('manage_stock', '!=', 'parent')
                    ->where(function ($inner) {
                        $inner->where('stock_quantity', '>', 0)
                            ->orWhere('backorders', '!=', 'no');
                    });
            });
        });
    }

    public function scopeOnBackorder(Builder $query): Builder
    {
        return $query->where('manage_stock', 'true')
            ->where('stock_quantity', '<=', 0)
            ->where('backorders', '!=', 'no');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('manage_stock', 'true')
            ->where('stock_quantity', '<=', 0)
            ->where('backorders', '=', 'no');
    }

    public function scopeLowStock(Builder $query, ?int $defaultThreshold = 5): Builder
    {
        return $query->where('manage_stock', 'true')
            ->where('stock_quantity', '>', 0)
            ->where(function ($q) {
                $q->whereNotNull('low_stock_threshold')
                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
            });
    }

    public function scopeStockStatus(Builder $query, string $status): Builder
    {
        return $query->where('stock_status', $status);
    }

    public function scopeWhereAttributeTerm(Builder $query, int $attributeTermId): Builder
    {
        return $query->whereHas('attributeTerms', fn ($q) => $q->where('attribute_term_id', $attributeTermId));
    }

    public function syncAttributes(array $attrs): void
    {
        $syncData = [];
        foreach ($attrs as $attr) {
            $attrId = $attr['attribute_id'] ?? null;
            $termId = $attr['attribute_term_id'] ?? null;

            // Only global terms go to the pivot table; custom terms stored in JSON only (matching WooCommerce non-taxonomy behavior)
            if ($attrId && $termId) {
                $syncData[$termId] = ['attribute_id' => $attrId];
            }
        }

        $this->attributeTerms()->sync($syncData);

        DB::table('product_variations')
            ->where('id', $this->id)
            ->update(['attributes' => json_encode($attrs)]);
    }

    public function getAttributeLabelAttribute(): string
    {
        $attrs = $this->getAttribute('attributes');

        if (! $attrs) {
            return '';
        }

        return collect($attrs)->pluck('name')->implode(' / ');
    }

    public function managesOwnStock(): bool
    {
        return $this->manage_stock === 'true' || $this->manage_stock === true || $this->manage_stock === 1 || $this->manage_stock === '1';
    }

    public function usesParentStock(): bool
    {
        return $this->manage_stock === 'parent';
    }

    public function isInStock(): bool
    {
        if ($this->usesParentStock()) {
            return $this->product ? $this->product->isInStock() : true;
        }

        if (! $this->managesOwnStock()) {
            return $this->stock_status !== 'outofstock';
        }

        return $this->stock_quantity > 0 || $this->backorders !== 'no';
    }

    public function isOnBackorder(): bool
    {
        if ($this->usesParentStock()) {
            return $this->product ? $this->product->isOnBackorder() : false;
        }

        return $this->managesOwnStock() && $this->stock_quantity <= 0 && $this->backorders !== 'no';
    }

    public function isLowStock(): bool
    {
        if (! $this->managesOwnStock()) {
            return false;
        }

        $threshold = $this->product?->low_stock_threshold;

        if ($threshold === null) {
            return false;
        }

        return $this->stock_quantity > 0 && $this->stock_quantity <= $threshold;
    }

    public function isOutOfStock(): bool
    {
        return ! $this->isInStock();
    }

    public function getEffectivePriceAttribute(): float
    {
        if ($this->active_price !== null) {
            return (float) $this->active_price;
        }

        $salePrice = $this->sale_price;

        if ($salePrice !== null && $salePrice !== '') {
            $saleFrom = $this->sale_from;
            $saleTo = $this->sale_to;
            $now = now();
            $inRange = true;

            if ($saleFrom && $saleTo) {
                $inRange = $now->between($saleFrom, $saleTo);
            } elseif ($saleFrom) {
                $inRange = $now->gte($saleFrom);
            } elseif ($saleTo) {
                $inRange = $now->lte($saleTo);
            }

            if ($inRange) {
                return (float) $salePrice;
            }
        }

        return (float) ($this->price ?? $this->product->price);
    }

    public function getDisplayPriceAttribute(): string
    {
        $effective = $this->effective_price;

        if ($this->compare_price && $this->compare_price > $effective) {
            return sprintf(
                '%s <s>%s</s>',
                config('shop.currency_symbol', '�').number_format($effective, 2),
                config('shop.currency_symbol', '�').number_format((float) $this->compare_price, 2)
            );
        }

        return config('shop.currency_symbol', '�').number_format($effective, 2);
    }
}
