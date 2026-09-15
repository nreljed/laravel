<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product?->id ?? 'NULL';
        $type = $this->input('type', 'simple');

        $rules = [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug,'.$productId,
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'sale_from' => 'nullable|date',
            'sale_to' => 'nullable|date|after_or_equal:sale_from',
            'sku' => 'nullable|string|max:100|unique:products,sku,'.$productId,
            'barcode' => 'nullable|string|max:100',
            'global_unique_id' => 'nullable|string|max:50',
            'vendor' => 'nullable|string|max:255',
            'incoming_stock' => 'nullable|integer|min:0',
            'hold_stock_minutes' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'track_stock' => 'boolean',
            'backorders' => 'nullable|in:no,notify,yes',
            'stock_status' => 'nullable|in:instock,outofstock,onbackorder',
            'status' => 'nullable|in:draft,published,publish,archived,future',
            'publish_date' => 'nullable|date|required_if:status,future',
            'type' => 'nullable|in:simple,variable,grouped,external,digital,service',
            'catalog_visibility' => 'nullable|in:visible,catalog,search,hidden',
            'virtual' => 'boolean',
            'downloadable' => 'boolean',
            'external_url' => 'nullable|string|url|max:2048',
            'button_text' => 'nullable|string|max:100',
            'is_featured' => 'boolean',
            'sold_individually' => 'boolean',
            'min_quantity' => 'nullable|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0|gt:min_quantity',
            'menu_order' => 'nullable|integer|min:0',
            'is_taxable' => 'boolean',
            'tax_class' => 'nullable|string|max:100',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:product_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:product_tags,id',
            'brands' => 'nullable|array',
            'brands.*' => 'exists:product_brands,id',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'og_image' => 'nullable|string|max:2048',
            'focus_keyword' => 'nullable|string|max:255',
            'seo_metadata' => 'nullable|array',
            'default_attributes' => 'nullable|array',
            'default_attributes.*' => 'exists:attribute_terms,id',
            'product_attributes' => 'nullable|array',
            'product_attributes.*.id' => 'nullable|exists:product_attributes,id',
            'product_attributes.*.attribute_id' => 'nullable|exists:attributes,id',
            'product_attributes.*.name' => 'required|string|max:255',
            'product_attributes.*.position' => 'integer|min:0',
            'product_attributes.*.visible' => 'boolean',
            'product_attributes.*.variation' => 'boolean',
            'product_attributes.*.options' => 'nullable|array',
            'grouped_items' => 'nullable|array',
            'grouped_items.*.product_id' => 'required|exists:products,id',
            'grouped_items.*.quantity' => 'required|integer|min:1',
            'downloads' => 'nullable|array',
            'downloads.*.name' => 'required|string|max:255',
            'downloads.*.file' => 'required|string',
            'downloads.*.download_limit' => 'nullable|integer|min:-1',
            'downloads.*.download_expiry' => 'nullable|integer|min:0',
        ];

        if ($type === 'variable') {
            $rules['price'] = 'nullable|numeric|min:0';
            $rules['stock_quantity'] = 'nullable|integer|min:0';
            $rules['weight'] = 'nullable|numeric|min:0';
        } elseif ($type === 'external') {
            $rules['price'] = 'nullable|numeric|min:0';
            $rules['stock_quantity'] = 'nullable|integer|min:0';
            $rules['weight'] = 'nullable|numeric|min:0';
            $rules['external_url'] = 'required|string|url|max:2048';
        } elseif ($type === 'grouped') {
            $rules['price'] = 'nullable|numeric|min:0';
            $rules['stock_quantity'] = 'nullable|integer|min:0';
            $rules['weight'] = 'nullable|numeric|min:0';
        } else {
            $rules['price'] = 'required|numeric|min:0';
            $rules['stock_quantity'] = 'nullable|integer|min:0';
            $rules['weight'] = 'nullable|numeric|min:0';
        }

        $rules['images'] = 'nullable|array';
        $rules['images.*.url'] = 'required_with:images|string';
        $rules['images.*.path'] = 'required_with:images|string';
        $rules['thumbnail_url'] = 'nullable|string';

        return $rules;
    }
}
