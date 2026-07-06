<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'short_description',
        'price', 'compare_price', 'cost_price', 'sale_price',
        'sale_from', 'sale_to',
        'min_variation_price', 'max_variation_price', 'price_hash',
        'sku', 'barcode', 'global_unique_id', 'vendor', 'stock_quantity', 'incoming_stock', 'low_stock_threshold',
        'hold_stock_minutes', 'track_stock', 'backorders', 'stock_status',
        'status', 'publish_date', 'type', 'catalog_visibility',
        'virtual', 'downloadable', 'external_url', 'button_text',
        'is_featured', 'sold_individually', 'min_quantity', 'max_quantity', 'menu_order',
        'is_taxable', 'tax_class',
        'weight', 'length', 'width', 'height',
        'images', 'thumbnail_url', 'attributes', 'seo',
        'default_attributes',
        'created_by', 'average_rating', 'reviews_count',
    ];

    protected $casts = [
        'price' => 'float',
        'compare_price' => 'float',
        'cost_price' => 'float',
        'sale_price' => 'float',
        'min_variation_price' => 'float',
        'max_variation_price' => 'float',
        'sale_from' => 'datetime',
        'sale_to' => 'datetime',
        'publish_date' => 'datetime',
        'stock_quantity' => 'integer',
        'incoming_stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'hold_stock_minutes' => 'integer',
        'track_stock' => 'boolean',
        'stock_status' => 'string',
        'backorders' => 'string',
        'virtual' => 'boolean',
        'downloadable' => 'boolean',
        'is_featured' => 'boolean',
        'sold_individually' => 'boolean',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'menu_order' => 'integer',
        'is_taxable' => 'boolean',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'images' => 'array',
        'attributes' => 'array',
        'seo' => 'array',
        'default_attributes' => 'array',
    ];

    protected $appends = ['active_price', 'on_sale'];

    public function getActivePriceAttribute(): float
    {
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

        return (float) ($this->price ?? 0);
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
        $inRange = true;

        if ($saleFrom && $saleTo) {
            $inRange = $now->between($saleFrom, $saleTo);
        } elseif ($saleFrom) {
            $inRange = $now->gte($saleFrom);
        } elseif ($saleTo) {
            $inRange = $now->lte($saleTo);
        }

        return $inRange;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Product $product) {
            if (! $product->slug) {
                $product->slug = Str::slug($product->name);
            }
            if (! $product->created_by && auth()->check()) {
                $product->created_by = auth()->id();
            }
        });

        static::saving(function (Product $product) {
            if ($product->track_stock && ! $product->isDirty('stock_status')) {
                if ($product->stock_quantity > 0) {
                    $product->stock_status = 'instock';
                } elseif ($product->backorders !== 'no') {
                    $product->stock_status = 'onbackorder';
                } else {
                    $product->stock_status = 'outofstock';
                }
            }
        });

        static::forceDeleting(function (Product $product) {
            $paths = collect($product->images ?? [])->pluck('path')->filter()->toArray();

            foreach ($product->variations as $variation) {
                $varPaths = collect($variation->images ?? [])->pluck('path')->filter()->toArray();
                $paths = array_merge($paths, $varPaths);
            }

            foreach (array_unique($paths) as $path) {
                Storage::disk('public')->delete($path);
            }
        });
    }

    // --- Price Range Caching (G2 + G3) ---

    public function syncPriceRange(): void
    {
        $prices = $this->variations()
            ->where('is_active', true)
            ->whereNotNull('active_price')
            ->pluck('active_price')
            ->map(fn ($p) => (float) $p);

        if ($prices->isEmpty()) {
            $this->min_variation_price = null;
            $this->max_variation_price = null;
            $this->price_hash = null;
        } else {
            $this->min_variation_price = $prices->min();
            $this->max_variation_price = $prices->max();
            // G8: price hash — md5 of sorted price list for quick invalidation
            $this->price_hash = md5($prices->sort()->values()->implode(','));
        }

        $this->saveQuietly();
    }

    public function getPriceRangeAttribute(): array
    {
        if ($this->min_variation_price !== null && $this->max_variation_price !== null) {
            return [
                'min' => $this->min_variation_price,
                'max' => $this->max_variation_price,
            ];
        }

        $prices = $this->variations()->active()->pluck('active_price')->filter()->map(fn ($p) => (float) $p);

        if ($prices->isEmpty()) {
            return ['min' => (float) $this->price, 'max' => (float) $this->price];
        }

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
        ];
    }

    public function getDisplayPriceAttribute(): string
    {
        $sym = config('shop.currency_symbol', '€');

        if (! $this->isVariable()) {
            return $sym.number_format((float) $this->price, 2);
        }

        $range = $this->price_range;

        if ($range['min'] === $range['max']) {
            return $sym.number_format($range['min'], 2);
        }

        return $sym.number_format($range['min'], 2).' – '.$sym.number_format($range['max'], 2);
    }

    // --- Relations ---

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_product');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_tag_product');
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(ProductBrand::class, 'product_product_brand');
    }

    public function globalAttributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_product');
    }

    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->orderBy('position');
    }

    public function variationAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->where('variation', true)->orderBy('position');
    }

    /** @deprecated Use productAttributes() instead */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_product');
    }

    public function attributeTerms(): BelongsToMany
    {
        return $this->belongsToMany(AttributeTerm::class, 'product_attribute_term');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(ProductDownload::class)->orderBy('sort_order');
    }

    public function groupedItems(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_grouped_products', 'group_product_id', 'product_id')
            ->withPivot('quantity', 'sort_order')
            ->orderByPivot('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    // --- Rating ---

    public function updateRatingCache(): void
    {
        $stats = $this->approvedReviews()
            ->selectRaw('COUNT(*) as count, COALESCE(AVG(rating), 0) as avg')
            ->first();

        $this->reviews_count = $stats->count;
        $this->average_rating = round((float) $stats->avg, 1);
        $this->saveQuietly();
    }

    // --- Scopes ---

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeLowStock($query)
    {
        return $query->where('track_stock', true)
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    public function scopeWhereVariationAttributeTerm(Builder $query, int $attributeTermId): Builder
    {
        return $query->whereHas('variations.attributeTerms', fn ($q) => $q->where('attribute_term_id', $attributeTermId));
    }

    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_stock', false)
                ->orWhere('stock_quantity', '>', 0)
                ->orWhere('backorders', '!=', 'no');
        });
    }

    public function scopeOnBackorder($query)
    {
        return $query->where('track_stock', true)
            ->where('stock_quantity', '<=', 0)
            ->where('backorders', '!=', 'no');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_stock', true)
                ->where('stock_quantity', '<=', 0)
                ->where('backorders', '=', 'no');
        });
    }

    public function scopeStockStatus($query, string $status)
    {
        return $query->where('stock_status', $status);
    }

    // --- Stock helpers ---

    public function isLowStock(): bool
    {
        if (! $this->track_stock || $this->low_stock_threshold === null) {
            return false;
        }

        return $this->stock_quantity > 0 && $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function isInStock(): bool
    {
        if (! $this->track_stock) {
            return $this->stock_status !== 'outofstock';
        }

        return $this->stock_quantity > 0 || $this->backorders !== 'no';
    }

    public function isOnBackorder(): bool
    {
        return $this->track_stock && $this->stock_quantity <= 0 && $this->backorders !== 'no';
    }

    public function isOutOfStock(): bool
    {
        return ! $this->isInStock();
    }

    // --- Type checks ---

    public function isSimple(): bool
    {
        return $this->type === 'simple';
    }

    public function isVariable(): bool
    {
        return $this->type === 'variable';
    }

    public function isGrouped(): bool
    {
        return $this->type === 'grouped';
    }

    public function isExternal(): bool
    {
        return $this->type === 'external';
    }

    public function isVirtual(): bool
    {
        return $this->virtual || $this->type === 'external' || $this->type === 'digital' || $this->type === 'service';
    }

    public function isDigital(): bool
    {
        return $this->type === 'digital';
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }

    public function isDownloadable(): bool
    {
        return $this->downloadable;
    }

    // --- Default variation (G6: updated for key-value format) ---

    public function getDefaultVariation(): ?ProductVariation
    {
        if (! $this->isVariable() || ! $this->default_attributes) {
            return $this->variations()->active()->first();
        }

        $defaults = $this->default_attributes;

        // Key-value format: { attribute_slug: term_slug } - WooCommerce compatible (G6)
        if ($this->isKeyValueDefaultAttributes($defaults)) {
            return $this->resolveDefaultByKeyValue($defaults);
        }

        // Legacy format: array of term IDs
        if (is_array($defaults)) {
            $defIds = array_map('intval', array_filter($defaults, fn ($v) => is_int($v) || ctype_digit((string) $v)));
            if (! empty($defIds)) {
                return $this->variations()->active()->whereHas('attributeTerms', function ($q) use ($defIds) {
                    $q->whereIn('attribute_term_id', $defIds);
                }, '=', count($defIds))->first()
                    ?? $this->variations()->active()->first();
            }
        }

        // Fallback: match by attribute name
        $names = collect($defaults)->values()->toArray();

        return $this->variations()->active()->get()->first(function ($v) use ($names) {
            $vNames = $v->attributeTerms()->with('attribute')->get()
                ->pluck('attribute.name')
                ->sort()
                ->values()
                ->toArray();

            return $names === $vNames;
        }) ?? $this->variations()->active()->first();
    }

    protected function isKeyValueDefaultAttributes(array $defaults): bool
    {
        if (empty($defaults)) {
            return false;
        }

        // Check if keys are strings (attribute slugs) rather than numeric
        $keys = array_keys($defaults);

        return ! empty($keys) && is_string($keys[0]);
    }

    protected function resolveDefaultByKeyValue(array $defaults): ?ProductVariation
    {
        $variations = $this->variations()->active()->with('attributeTerms.attribute')->get();

        foreach ($variations as $variation) {
            $matched = true;
            foreach ($defaults as $attrSlug => $termSlug) {
                $hasMatch = $variation->attributeTerms->contains(function ($term) use ($attrSlug, $termSlug) {
                    $attr = $term->attribute;
                    if (! $attr) {
                        return false;
                    }

                    return Str::slug($attr->name) === $attrSlug && $term->slug === $termSlug;
                });

                if (! $hasMatch) {
                    $matched = false;
                    break;
                }
            }

            if ($matched) {
                return $variation;
            }
        }

        return $this->variations()->active()->first();
    }
}
