<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFieldValue extends Model
{
    protected $fillable = ['product_id', 'product_field_id', 'value', 'sort_order'];

    protected $casts = [
        'value' => 'array',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ProductField::class, 'product_field_id');
    }

    public function isEmpty(): bool
    {
        return $this->value === null || $this->value === '' || $this->value === [];
    }

    public function formattedValue(): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }

        if (is_bool($this->value)) {
            return $this->value ? 'Yes' : 'No';
        }

        if (! is_scalar($this->value)) {
            return null;
        }

        $value = (string) $this->value;

        return $this->field?->isSelect() ? $this->field->optionLabel($value) : $value;
    }
}
