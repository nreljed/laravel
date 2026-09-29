<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nreljed\Platform\Services\MediaService;
use Nreljed\ShopModule\Database\Factories\ProductFactory;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Storefront catalog visibility - the four values a shopkeeper already
     * expects from WooCommerce, so the column reads the same in the admin and
     * survives a WooCommerce import.
     *
     * They live here, on the model, because every surface that has to answer
     * "may this shopper see this product?" used to carry its own copy of the
     * list: the storefront catalog, the homepage rails, related products, the
     * public JSON API. A value added to the database without being added to
     * those lists disappeared from the shop with no error anywhere.
     *
     * The rules:
     *  - `visible` — listed, searchable, and reachable by direct link.
     *  - `catalog` — listed, searchable, and reachable by direct link.
     *  - `search`  — searchable and reachable by direct link, never listed.
     *  - `hidden`  — not listed, not searchable, and 404 on direct link.
     *
     * **`catalog` deliberately does not mean what WooCommerce means by it.**
     * In WooCommerce, `catalog` is "exclude from shop and category pages but
     * keep it searchable" - the same job this schema gives to `search`. Here
     * `catalog` means "an ordinary listed product" and `search` is the
     * "findable but never listed" value. So an imported WooCommerce store
     * reverses the two: products marked `catalog` upstream appear in listings
     * here, and products marked `search` upstream stay out of them.
     *
     * This was not changed to match WooCommerce, because the two sets are
     * stable, both are covered by tests, and the column is populated with
     * existing data - flipping the meaning would silently pull or push live
     * products. An import has to translate the values instead; the mapping is
     * the whole reason the divergence is written down here.
     *
     * @var list<string>
     */
    public const VISIBLE = 'visible';

    public const CATALOG = 'catalog';

    public const SEARCH = 'search';

    public const HIDDEN = 'hidden';

    /** @var list<string> */
    public const CATALOG_VISIBILITIES = [self::VISIBLE, self::CATALOG, self::SEARCH, self::HIDDEN];

    /** @var list<string> */
    public const LISTING_VISIBILITIES = [self::VISIBLE, self::CATALOG];

    /** @var list<string> */
    public const SEARCH_VISIBILITIES = [self::VISIBLE, self::CATALOG, self::SEARCH];

    protected $fillable = [
        'name', 'slug', 'description', 'short_description',
        'price', 'compare_price', 'cost_price', 'sale_price',
        'sale_from', 'sale_to',
        'min_variation_price', 'max_variation_price', 'price_hash',
        'sku', 'barcode', 'global_unique_id', 'mpn', 'vendor', 'stock_quantity', 'incoming_stock', 'low_stock_threshold',
        'hold_stock_minutes', 'track_stock', 'backorders', 'stock_status',
        'status', 'publish_date', 'unpublish_at', 'type', 'catalog_visibility',
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
        'unpublish_at' => 'datetime',
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
        'average_rating' => 'float',
        'reviews_count' => 'integer',
    ];

    protected $appends = ['active_price', 'on_sale', 'is_in_stock', 'price_range'];

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }

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

    /**
     * Storage paths referenced by this product, including responsive variants.
     * Used by `core:cleanup-orphan-media` to tell live media from orphans.
     *
     * @return list<string>
     */
    public function getReferencedPaths(): array
    {
        $paths = $this->getPrimaryReferencedPaths();

        $service = app(MediaService::class);

        foreach ($paths as $primary) {
            $paths = array_merge($paths, $service->variantPathsForPath($primary));
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * Primary (non-variant) storage paths referenced by the product.
     *
     * @return list<string>
     */
    public function getPrimaryReferencedPaths(): array
    {
        $paths = [];

        if ($this->thumbnail_url) {
            $paths[] = self::urlToPath($this->thumbnail_url);
        }

        foreach ($this->images ?? [] as $image) {
            if (! empty($image['path'])) {
                $paths[] = $image['path'];
            }
        }

        foreach ($this->variations as $variation) {
            foreach ($variation->images ?? [] as $image) {
                if (! empty($image['path'])) {
                    $paths[] = $image['path'];
                }
            }
        }

        return array_filter($paths);
    }

    /**
     * Convert a stored URL (relative or absolute) into a storage-relative path.
     */
    protected static function urlToPath(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $prefix = '/storage/';

        if (str_starts_with($url, $prefix)) {
            return substr($url, strlen($prefix));
        }

        if (str_starts_with($url, 'http')) {
            $parsed = parse_url($url, PHP_URL_PATH);

            return $parsed && str_starts_with($parsed, $prefix)
                ? substr($parsed, strlen($prefix))
                : null;
        }

        if (! str_contains($url, '/') || str_contains(str_replace('\\', '/', $url), '../')) {
            return null;
        }

        return $url;
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
                'min' => (float) $this->min_variation_price,
                'max' => (float) $this->max_variation_price,
            ];
        }

        $prices = $this->isVariable() ? $this->variationPrices() : collect();

        // Grouped products own no price: their price comes from the bundled items.
        if ($prices->isEmpty() && $this->isGrouped()) {
            $prices = $this->groupedItemPrices();
        }

        if ($prices->isEmpty()) {
            return ['min' => (float) $this->price, 'max' => (float) $this->price];
        }

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
        ];
    }

    /**
     * Active variation prices, reusing an already-loaded relation to avoid N+1.
     *
     * @return Collection<int, float>
     */
    protected function variationPrices(): Collection
    {
        $prices = $this->relationLoaded('variations')
            ? $this->variations->where('is_active', true)->pluck('active_price')
            : $this->variations()->active()->pluck('active_price');

        return $prices->filter()->map(fn ($p) => (float) $p)->values();
    }

    /**
     * Bundled item prices for grouped products, reusing an already-loaded relation.
     *
     * Only children a shopper can actually be charged are counted. A `hidden` or
     * unpublished child is not payable, so letting it into the range made a kit
     * advertise "EUR 10 - EUR 5000" with nothing in that span purchasable, and
     * the same figure feeds the price filter's bounds.
     *
     * The two branches apply the same rule in the two forms it can be asked
     * for: the instance predicate `isVisibleOnStorefront()` when the relation is
     * already in memory, and the matching `published()->reachable()` scopes when
     * it has to be fetched. Filtering explicitly rather than trusting whatever
     * eager load the caller happened to use keeps the value identical either
     * way - a storefront that constrained the load and a dashboard that did not
     * would otherwise advertise different prices for the same kit.
     *
     * @return Collection<int, float>
     */
    protected function groupedItemPrices(): Collection
    {
        $prices = $this->relationLoaded('groupedItems')
            ? $this->groupedItems
                ->filter(fn (self $child) => $child->isVisibleOnStorefront())
                ->pluck('price')
            : $this->groupedItems()
                ->published()
                ->reachable()
                ->pluck('products.price');

        return $prices->filter()->map(fn ($p) => (float) $p)->values();
    }

    public function getDisplayPriceAttribute(): string
    {
        $sym = config('shop.currency_symbol', '€');

        if (! $this->isVariable() && ! $this->isGrouped()) {
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

    public function productFieldValues(): HasMany
    {
        return $this->hasMany(ProductFieldValue::class)->orderBy('sort_order')->orderBy('id');
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

    public function priceRows(): HasMany
    {
        return $this->hasMany(ProductPrice::class)->whereNull('product_variation_id');
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

    /**
     * Products this product up-sells (the bigger/better alternative) (PR 11B).
     */
    public function upSales(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', ProductRelation::TYPE_UP_SELL)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * Products this product cross-sells (bought alongside it) (PR 11B).
     */
    public function crossSales(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', ProductRelation::TYPE_CROSS_SELL)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * Every merchant-curated relation in `sort_order`, cross-sells and
     * up-sells mixed.
     *
     * The admin editor keeps the two intents separate, but the storefront rail
     * is a row of cards either way, so it reads this and never has to care
     * which merchandising label a product wears.
     */
    public function curatedRelations(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_relations', 'product_id', 'related_product_id')
            ->withPivot('type', 'sort_order')
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
        return $query->where('status', 'published')
            ->where(function (Builder $q) {
                $q->whereNull('unpublish_at')
                    ->orWhere('unpublish_at', '>', now());
            });
    }

    /**
     * Instance-level storefront gate, mirroring the time-aware `published()`
     * scope. Used by the storefront show route for already-loaded models
     * (a scheduled auto-unpublish hides the product even before the cron
     * runtime service flips its status.)
     *
     * The gate is "reachable by direct link", which is deliberately wider than
     * "listed": a `search`-only product has no listing of its own, so a shopper
     * who followed a link still has to land on something. `hidden` is the one
     * value that is unreachable, because serving it would confirm the product
     * exists at a URL an editor asked to keep out of the shop.
     */
    public function isVisibleOnStorefront(): bool
    {
        if (! $this->isPublishedNow()) {
            return false;
        }

        return ! $this->isHiddenFromStorefront();
    }

    /**
     * Whether the cart and checkout may sell this product.
     *
     * Same rule as the storefront gate today - a product a shopper cannot
     * reach cannot be bought either - kept as its own name because the two
     * answer different questions and will drift apart (a discontinued flag, a
     * purchase-only audience). Every write path that turns a `product_id` into
     * a line item must go through here: the storefront page 404ing is not a
     * lock on the public cart endpoint.
     */
    public function isPurchasable(): bool
    {
        return $this->isVisibleOnStorefront();
    }

    public function isHiddenFromStorefront(): bool
    {
        return $this->catalog_visibility === self::HIDDEN;
    }

    protected function isPublishedNow(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        return $this->unpublish_at === null || $this->unpublish_at->gt(now());
    }

    /**
     * Restrict a query to products a shopper may browse in a listing.
     *
     * Covers the homepage rails, the catalog page and a category page. A
     * search-only product belongs in none of them: it exists so a product can
     * be found by name, not so it can appear under every category.
     *
     * @param  Builder  $query
     */
    public function scopeInListing($query)
    {
        return $query->whereIn('catalog_visibility', self::LISTING_VISIBILITIES);
    }

    /**
     * Restrict a query to products a shopper may reach by URL.
     *
     * The set behind `isVisibleOnStorefront()`: everything except `hidden`. It
     * is for surfaces that are neither a browse nor a search - a saved
     * wishlist, a picker for staff taking a phone order - where the shopper (or
     * the staff member) already knows which product they mean, and the question
     * is only whether the shop still offers it.
     *
     * @param  Builder  $query
     */
    public function scopeReachable($query)
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('catalog_visibility')
                ->orWhereNot('catalog_visibility', self::HIDDEN);
        });
    }

    /**
     * Restrict a query to products a shopper may find through a search.
     *
     * The listing set plus search-only products. A caller's search term is
     * what earns the wider set; without one the caller belongs in
     * `inListing()`.
     *
     * @param  Builder  $query
     */
    public function scopeInSearchResults($query)
    {
        return $query->whereIn('catalog_visibility', self::SEARCH_VISIBILITIES);
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

    public function getIsInStockAttribute(): bool
    {
        return $this->isInStock();
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        // Try slug first, fallback to ID for backward compatibility
        $product = $this->where('slug', $value)->first();
        if ($product) {
            return $product;
        }

        return $this->where('id', $value)->first();
    }

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
