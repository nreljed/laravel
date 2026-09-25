<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Nreljed\ShopModule\Models\ProductField;

class StoreProductFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productField = $this->route('productField');
        $fieldId = $productField?->id ?? 'NULL';

        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:product_fields,slug,'.$fieldId,
            'type' => 'required|in:'.implode(',', ProductField::TYPES),
            'description' => 'nullable|string|max:1000',
            'options' => 'nullable|array|max:50',
            'options.*.value' => 'nullable|string|max:100',
            'options.*.label' => 'nullable|string|max:100',
            'default_value' => 'nullable',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'show_on_storefront' => 'boolean',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = (string) $this->input('type');
                $optionValues = $this->optionValues();

                if ($this->slugTaken()) {
                    $validator->errors()->add('name', 'A product field with this name already exists.');
                }

                if ($type === 'select') {
                    if ($optionValues->isEmpty()) {
                        $validator->errors()->add('options', 'A select field needs at least one option.');
                    } elseif ($optionValues->duplicates()->isNotEmpty()) {
                        $validator->errors()->add('options', 'Option values must be unique.');
                    }
                }

                $default = $this->input('default_value');

                if ($default === null || $default === '') {
                    return;
                }

                $message = ProductField::valueError($type, $optionValues->all(), $default);

                if ($message !== null) {
                    $validator->errors()->add('default_value', 'The default value '.$message.'.');
                }
            },
        ];
    }

    private function optionValues(): Collection
    {
        return collect($this->input('options', []))
            ->map(fn ($option) => is_array($option) ? trim((string) ($option['value'] ?? '')) : trim((string) $option))
            ->filter();
    }

    private function slugTaken(): bool
    {
        $slug = trim((string) $this->input('slug'));

        if ($slug === '') {
            $slug = Str::slug((string) $this->input('name'));
        }

        if ($slug === '') {
            return false;
        }

        $query = ProductField::query()->where('slug', $slug);
        $productField = $this->route('productField');

        if ($productField !== null) {
            $query->whereKeyNot($productField->getKey());
        }

        return $query->exists();
    }
}
