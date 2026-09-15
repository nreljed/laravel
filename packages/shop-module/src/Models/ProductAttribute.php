<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductAttribute extends Model
{
    protected $fillable = [
        'product_id', 'attribute_id', 'name', 'position',
        'visible', 'variation', 'options',
    ];

    protected $casts = [
        'position' => 'integer',
        'visible' => 'boolean',
        'variation' => 'boolean',
        'options' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function getTermsAttribute(): array
    {
        if ($this->attribute_id) {
            return $this->attribute->terms()->orderBy('sort_order')->get()->toArray();
        }

        return collect($this->options ?? [])->map(fn ($opt) => [
            'id' => null,
            'name' => $opt,
            'slug' => Str::slug($opt),
        ])->toArray();
    }

    public function isGlobal(): bool
    {
        return $this->attribute_id !== null;
    }

    public function isCustom(): bool
    {
        return $this->attribute_id === null;
    }
}
