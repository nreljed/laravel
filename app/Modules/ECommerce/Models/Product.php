<?php

namespace App\Modules\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'short_description',
        'price', 'compare_price', 'cost_price',
        'sku', 'barcode', 'stock_quantity', 'low_stock_threshold',
        'track_stock', 'allow_backorders',
        'status', 'type', 'is_featured', 'is_taxable', 'tax_class',
        'weight', 'length', 'width', 'height',
        'images', 'thumbnail_url', 'attributes', 'variants', 'seo',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'track_stock' => 'boolean',
        'allow_backorders' => 'boolean',
        'is_featured' => 'boolean',
        'is_taxable' => 'boolean',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'images' => 'array',
        'attributes' => 'array',
        'variants' => 'array',
        'seo' => 'array',
    ];

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
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

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
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_stock', false)
                ->orWhere('stock_quantity', '>', 0)
                ->orWhere('allow_backorders', true);
        });
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function isInStock(): bool
    {
        return ! $this->track_stock || $this->stock_quantity > 0 || $this->allow_backorders;
    }
}
