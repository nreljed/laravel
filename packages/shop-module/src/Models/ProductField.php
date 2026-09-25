<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductField extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'description', 'options', 'default_value',
        'is_required', 'is_active', 'show_on_storefront', 'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'default_value' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'show_on_storefront' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const TYPES = ['text', 'textarea', 'number', 'boolean', 'date', 'select'];

    public static function typeLabels(): array
    {
        return [
            'text' => 'Text',
            'textarea' => 'Textarea',
            'number' => 'Number',
            'boolean' => 'Yes / No',
            'date' => 'Date',
            'select' => 'Select',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (ProductField $field) {
            if (! $field->slug) {
                $field->slug = Str::slug($field->name);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductFieldValue::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isSelect(): bool
    {
        return $this->type === 'select';
    }

    public function optionValues(): array
    {
        return collect($this->options ?? [])
            ->map(fn ($option) => is_array($option) ? ($option['value'] ?? null) : $option)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->values()
            ->all();
    }

    public function optionLabel(string $value): string
    {
        foreach ($this->options ?? [] as $option) {
            if (is_array($option) && (string) ($option['value'] ?? '') === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return $value;
    }

    public static function normalizeValue(string $type, mixed $raw): string|bool|null
    {
        if ($type === 'boolean') {
            if ($raw === null || $raw === '') {
                return null;
            }

            return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if (! is_scalar($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        return $value === '' ? null : $value;
    }

    public static function valueError(string $type, array $optionValues, mixed $raw): ?string
    {
        $value = self::normalizeValue($type, $raw);

        if ($value === null) {
            return null;
        }

        return match ($type) {
            'number' => is_numeric($value) ? null : 'must be a number',
            'date' => self::isDate((string) $value) ? null : 'must be a valid date',
            'select' => in_array($value, $optionValues, true) ? null : 'must match one of the options',
            default => null,
        };
    }

    private static function isDate(string $value): bool
    {
        $date = date_create($value);

        return $date !== false && date_format($date, 'Y-m-d') === substr($value, 0, 10);
    }
}
