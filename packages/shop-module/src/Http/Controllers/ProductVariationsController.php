<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\AttributeTerm;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;

class ProductVariationsController extends Controller
{
    public function index(Product $product)
    {
        $this->authorize('view', $product);

        $variations = $product->variations()->orderBy('sort_order')->get();

        return response()->json($variations->map(fn ($v) => array_merge($v->toArray(), ['is_visible' => $v->isVisible()]))->values());
    }

    public function store(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate($this->rules());

        // G4: Normalize sale dates to day boundaries
        $validated = $this->normalizeSaleDates($validated);

        $sortOrder = $product->variations()->max('sort_order') + 1;
        $attrs = $validated['attributes'] ?? [];

        unset($validated['attributes']);

        $variation = $product->variations()->create(array_merge($validated, [
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]));

        if (! empty($attrs)) {
            $variation->syncAttributes($attrs);
        }

        $product->update(['type' => 'variable']);

        return response()->json(array_merge($variation->fresh()->toArray(), ['is_visible' => $variation->fresh()->isVisible()]));
    }

    public function update(Request $request, Product $product, ProductVariation $variation)
    {
        $this->authorize('update', $product);

        $validated = $request->validate($this->rules($variation->id));

        // G4: Normalize sale dates to day boundaries
        $validated = $this->normalizeSaleDates($validated);

        $attrs = $validated['attributes'] ?? null;
        unset($validated['attributes']);

        $variation->update($validated);

        if ($attrs !== null) {
            $variation->syncAttributes($attrs);
        }

        return response()->json(array_merge($variation->fresh()->toArray(), ['is_visible' => $variation->fresh()->isVisible()]));
    }

    public function destroy(Product $product, ProductVariation $variation)
    {
        $this->authorize('update', $product);

        $variation->delete();

        if ($product->variations()->count() === 0) {
            $product->update(['type' => 'simple']);
        }

        return response()->json(['success' => true]);
    }

    public function destroyBulk(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'exists:product_variations,id',
        ]);

        $count = $product->variations()->whereIn('id', $validated['selected_ids'])->delete();

        if ($product->variations()->count() === 0) {
            $product->update(['type' => 'simple']);
        }

        return response()->json(['success' => true, 'deleted' => $count]);
    }

    public function generate(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'attribute_ids' => 'nullable|array',
            'attribute_ids.*' => 'exists:attributes,id',
            'term_ids' => 'nullable|array',
            'term_ids.*' => 'exists:attribute_terms,id',
            'custom_attribute_ids' => 'nullable|array',
            'custom_attribute_ids.*' => 'exists:product_attributes,id',
            'custom_option_names' => 'nullable|array',
            'custom_option_names.*' => 'string',
        ]);

        $selectedGlobalIds = $validated['attribute_ids'] ?? [];
        $termIds = $validated['term_ids'] ?? [];
        $selectedCustomIds = $validated['custom_attribute_ids'] ?? [];
        $customOptionNames = $validated['custom_option_names'] ?? [];

        if (empty($selectedGlobalIds) && empty($termIds) && empty($selectedCustomIds)) {
            return response()->json(['message' => 'Select at least one attribute.'], 422);
        }

        // Validate selected IDs belong to this product's variation attributes
        $variationAttrs = $product->variationAttributes()->get();

        $validGlobalIds = $variationAttrs->whereNotNull('attribute_id')->pluck('attribute_id')->unique()->values()->toArray();

        $groups = [];

        // Global attribute terms — use specific term_ids if provided, else all terms per attribute_id
        if (! empty($termIds)) {
            $termIds = array_unique($termIds);
            $terms = AttributeTerm::whereIn('id', $termIds)
                ->whereIn('attribute_id', $validGlobalIds)
                ->orderBy('sort_order')
                ->get();

            if ($terms->isEmpty()) {
                return response()->json(['message' => 'Invalid term selection.'], 422);
            }

            $termLookup = $terms->keyBy('id');
            $grouped = $terms->groupBy('attribute_id');
            $attrOrder = [];

            foreach ($termIds as $tid) {
                if (isset($termLookup[$tid])) {
                    $attrId = $termLookup[$tid]->attribute_id;
                    if (! in_array($attrId, $attrOrder)) {
                        $attrOrder[] = $attrId;
                    }
                }
            }

            foreach ($attrOrder as $attrId) {
                $termGroup = $grouped[$attrId] ?? null;
                if (! $termGroup) {
                    continue;
                }

                $group = $termGroup->map(fn ($t) => [
                    'attribute_id' => $attrId,
                    'attribute_term_id' => $t->id,
                    'product_attribute_id' => null,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ])->values()->toArray();

                if (! empty($group)) {
                    $groups[] = $group;
                }
            }
        } elseif (! empty($selectedGlobalIds)) {
            $invalidGlobal = array_diff($selectedGlobalIds, $validGlobalIds);
            if (! empty($invalidGlobal)) {
                return response()->json(['message' => 'Invalid attribute selection.'], 422);
            }

            $globalTerms = AttributeTerm::whereIn('attribute_id', $selectedGlobalIds)
                ->orderBy('sort_order')
                ->get()
                ->groupBy('attribute_id');

            foreach ($selectedGlobalIds as $attrId) {
                $terms = $globalTerms[$attrId] ?? null;
                if (! $terms) {
                    continue;
                }

                $group = $terms->map(fn ($t) => [
                    'attribute_id' => $attrId,
                    'attribute_term_id' => $t->id,
                    'product_attribute_id' => null,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ])->values()->toArray();

                if (! empty($group)) {
                    $groups[] = $group;
                }
            }
        }

        // Custom attribute terms (from product_attributes.options JSON)
        if (! empty($selectedCustomIds)) {
            $validCustomIds = $variationAttrs->whereNull('attribute_id')->pluck('id')->values()->toArray();
            $invalidCustom = array_diff($selectedCustomIds, $validCustomIds);
            if (! empty($invalidCustom)) {
                return response()->json(['message' => 'Invalid custom attribute selection.'], 422);
            }

            $customAttrs = $variationAttrs->whereIn('id', $selectedCustomIds);

            foreach ($selectedCustomIds as $customId) {
                $pa = $customAttrs->firstWhere('id', $customId);
                if (! $pa) {
                    continue;
                }

                $options = $pa->options ?? [];

                if (! empty($customOptionNames)) {
                    $options = array_values(array_intersect($options, $customOptionNames));
                }

                if (empty($options)) {
                    continue;
                }

                $group = [];
                foreach ($options as $opt) {
                    $group[] = [
                        'attribute_id' => null,
                        'attribute_term_id' => null,
                        'product_attribute_id' => $pa->id,
                        'name' => $opt,
                        'slug' => Str::slug($opt),
                    ];
                }

                $groups[] = $group;
            }
        }

        $combinations = $this->cartesianProduct($groups);
        $existingVariations = $product->variations()->get();

        $created = 0;
        foreach ($combinations as $combo) {
            $exists = $existingVariations->contains(function ($variation) use ($combo) {
                $varAttrs = $variation->getAttribute('attributes') ?? [];

                if (count($varAttrs) !== count($combo)) {
                    return false;
                }

                foreach ($combo as $c) {
                    $match = false;
                    foreach ($varAttrs as $v) {
                        if (isset($c['attribute_term_id']) && isset($v['attribute_term_id'])) {
                            if ((int) $c['attribute_term_id'] === (int) $v['attribute_term_id']) {
                                $match = true;
                                break;
                            }
                        } elseif (isset($c['product_attribute_id']) && isset($v['product_attribute_id'])) {
                            if ((int) $c['product_attribute_id'] === (int) $v['product_attribute_id']
                                && ($c['slug'] ?? '') === ($v['slug'] ?? '')) {
                                $match = true;
                                break;
                            }
                        } elseif (($c['name'] ?? '') === ($v['name'] ?? '')) {
                            $match = true;
                            break;
                        }
                    }
                    if (! $match) {
                        return false;
                    }
                }

                return true;
            });

            if (! $exists) {
                $variation = $product->variations()->create([
                    'is_active' => true,
                    'sort_order' => $product->variations()->max('sort_order') + 1,
                ]);

                $variation->syncAttributes($combo);
                $created++;
            }
        }

        if ($created > 0) {
            $product->update(['type' => 'variable']);
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'total' => count($combinations),
        ]);
    }

    public function storeBulk(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'variations' => 'required|array',
            'variations.*.id' => 'nullable|exists:product_variations,id',
            'variations.*.sku' => 'nullable|string|max:100',
            'variations.*.price' => 'nullable|numeric|min:0',
            'variations.*.compare_price' => 'nullable|numeric|min:0',
            'variations.*.sale_price' => 'nullable|numeric|min:0',
            'variations.*.sale_from' => 'nullable|date',
            'variations.*.sale_to' => 'nullable|date',
            'variations.*.stock_quantity' => 'nullable|integer|min:0',
            'variations.*.manage_stock' => 'nullable',
            'variations.*.backorders' => 'nullable|in:no,notify,yes',
            'variations.*.stock_status' => 'nullable|in:instock,outofstock,onbackorder',
            'variations.*.weight' => 'nullable|numeric|min:0',
            'variations.*.length' => 'nullable|numeric|min:0',
            'variations.*.width' => 'nullable|numeric|min:0',
            'variations.*.height' => 'nullable|numeric|min:0',
            'variations.*.description' => 'nullable|string',
            'variations.*.is_active' => 'boolean',
            'variations.*.image_url' => 'nullable|string|max:500',
            'variations.*.images' => 'nullable|array',
            'variations.*.images.*.url' => 'required_with:variations.*.images|string',
            'variations.*.images.*.path' => 'nullable|string',
            'variations.*.images.*.featured' => 'nullable|boolean',
            'variations.*.attributes' => 'nullable|array',
            'variations.*.sort_order' => 'integer',
            'variations.*.visible' => 'nullable|boolean',
            'selected_ids' => 'nullable|array',
            'selected_ids.*' => 'exists:product_variations,id',
            'bulk_edit' => 'nullable|boolean',
        ]);

        $isBulkEdit = $validated['bulk_edit'] ?? false;
        $updatedIds = [];
        $sortOrder = $product->variations()->max('sort_order');

        foreach ($validated['variations'] as $data) {
            $sortOrder++;
            $data['sort_order'] = $data['sort_order'] ?? $sortOrder;

            $data = $this->normalizeSaleDates($data);

            $attrs = $data['attributes'] ?? null;
            unset($data['attributes']);

            if (! empty($data['id'])) {
                $variation = $product->variations()->find($data['id']);
                if ($variation) {
                    $variation->update($data);
                    if ($attrs !== null) {
                        $variation->syncAttributes($attrs);
                    }
                    $updatedIds[] = $variation->id;
                }
            } else {
                $variation = $product->variations()->create(array_merge($data, [
                    'is_active' => $data['is_active'] ?? true,
                ]));
                if (! empty($attrs)) {
                    $variation->syncAttributes($attrs);
                }
                $updatedIds[] = $variation->id;
            }
        }

        if (! $isBulkEdit) {
            $product->variations()->whereNotIn('id', $updatedIds)->delete();
        }

        if ($product->variations()->count() > 0) {
            $product->update(['type' => 'variable']);
        }

        return response()->json([
            'success' => true,
            'variations' => $product->variations()->orderBy('sort_order')->get(),
        ]);
    }

    public function reorder(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'variations' => 'required|array',
            'variations.*.id' => 'required|exists:product_variations,id',
            'variations.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['variations'] as $item) {
            ProductVariation::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function rules(?int $id = null): array
    {
        return [
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'global_unique_id' => 'nullable|string|max:50',
            'mpn' => 'nullable|string|max:100',
            'price' => 'nullable|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'sale_from' => 'nullable|date',
            'sale_to' => 'nullable|date|after_or_equal:sale_from',
            'stock_quantity' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*.url' => 'required|string',
            'images.*.path' => 'nullable|string',
            'images.*.featured' => 'nullable|boolean',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'visible' => 'nullable|boolean',
            'manage_stock' => 'nullable|in:parent,true,false,1,0',
            'backorders' => 'nullable|in:no,notify,yes',
            'attributes' => 'nullable|array',
            'attributes.*.attribute_id' => 'nullable|exists:attributes,id',
            'attributes.*.attribute_term_id' => 'nullable|exists:attribute_terms,id',
            'attributes.*.product_attribute_id' => 'nullable|exists:product_attributes,id',
            'attributes.*.name' => 'required|string',
        ];
    }

    /**
     * G4: Normalize sale_from to 00:00:00 and sale_to to 23:59:59 for day-boundary semantics.
     */
    private function normalizeSaleDates(array $data): array
    {
        if (! empty($data['sale_from'])) {
            $ts = strtotime($data['sale_from']);
            if ($ts !== false) {
                $data['sale_from'] = date('Y-m-d 00:00:00', $ts);
            }
        }

        if (! empty($data['sale_to'])) {
            $ts = strtotime($data['sale_to']);
            if ($ts !== false) {
                $data['sale_to'] = date('Y-m-d 23:59:59', $ts);
            }
        }

        return $data;
    }

    private function cartesianProduct(array $groups): array
    {
        if (empty($groups)) {
            return [];
        }

        $result = [[]];
        foreach ($groups as $group) {
            $append = [];
            foreach ($result as $product) {
                foreach ($group as $item) {
                    $append[] = array_merge($product, [$item]);
                }
            }
            $result = $append;
        }

        return $result;
    }
}
