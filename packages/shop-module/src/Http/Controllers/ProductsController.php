<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Notifications\AppNotification;
use Nreljed\Platform\Services\MediaService;
use Nreljed\Platform\Services\NotificationService;
use Nreljed\Platform\Services\UploadProfiles;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductAttribute;
use Nreljed\ShopModule\Models\ProductBrand;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\ProductDownload;
use Nreljed\ShopModule\Models\ProductTag;
use Nreljed\ShopModule\Requests\AdjustStockRequest;
use Nreljed\ShopModule\Requests\ImportProductRequest;
use Nreljed\ShopModule\Requests\StoreProductRequest;
use Nreljed\ShopModule\Services\InventoryService;
use Nreljed\ShopModule\Services\WebhookService;

class ProductsController extends Controller
{
    protected NotificationService $notificationService;

    protected InventoryService $inventoryService;

    protected MediaService $mediaService;

    protected WebhookService $webhookService;

    public function __construct(
        NotificationService $notificationService,
        InventoryService $inventoryService,
        MediaService $mediaService,
        ?WebhookService $webhookService = null,
    ) {
        $this->notificationService = $notificationService;
        $this->inventoryService = $inventoryService;
        $this->mediaService = $mediaService;
        $this->webhookService = $webhookService ?? app(WebhookService::class);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::with('categories', 'tags', 'brands');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->get('category_id')) {
            $query->whereHas('categories', fn ($q) => $q->where('product_categories.id', $categoryId));
        }

        $products = $query->orderBy('created_at', 'desc')->get();

        return Inertia::render('shop/products/index', [
            'products' => $products,
            'categories' => ProductCategory::active()->get(),
            'tags' => ProductTag::active()->get(),
            'brands' => ProductBrand::active()->get(),
            'nonce' => csrf_token(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Product::class);

        return Inertia::render('shop/products/form', [
            'product' => null,
            'categories' => ProductCategory::active()->get(),
            'tags' => ProductTag::active()->get(),
            'brands' => ProductBrand::active()->get(),
            'attributes' => Attribute::with('terms')->orderBy('sort_order')->get(),
            'variations' => [],
            'productAttributes' => [],
            'allProducts' => Product::select('id', 'name', 'sku', 'type', 'price')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();
        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        $validated = $this->applyScheduleStatus($validated);

        $validated = $this->mergeMetaFields($validated);

        $product = Product::create($validated);

        if (! empty($validated['categories'])) {
            $product->categories()->sync($validated['categories']);
        }

        if (! empty($validated['tags'])) {
            $product->tags()->sync($validated['tags']);
        }

        if (! empty($validated['brands'])) {
            $product->brands()->sync($validated['brands']);
        }

        if (! empty($validated['product_attributes'])) {
            $this->syncProductAttributes($product, $validated['product_attributes']);
        }

        if (! empty($validated['grouped_items'])) {
            $this->syncGroupedItems($product, $validated['grouped_items']);
        }

        if (! empty($validated['downloads'])) {
            $this->syncDownloads($product, $validated['downloads']);
        }

        $this->webhookService->dispatch(
            \Nreljed\ShopModule\Events\WebhookEvents::PRODUCT_CREATED,
            ['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku],
        );

        return redirect()->route('dashboard.shop.products.index')
            ->with('status', 'Product created successfully.');
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        $product->load([
            'categories',
            'reviews' => fn ($q) => $q->with('user')->latest(),
        ]);

        $userReview = null;
        if ($user = request()->user()) {
            $userReview = $product->reviews->firstWhere('user_id', $user->id);
        }

        return Inertia::render('shop/products/show', [
            'product' => $product,
            'userReview' => $userReview,
        ]);
    }

    public function edit(Product $product)
    {
        $this->authorize('update', $product);

        $product->load('categories', 'tags', 'brands', 'variations', 'productAttributes', 'groupedItems', 'downloads');

        return Inertia::render('shop/products/form', [
            'product' => $product,
            'categories' => ProductCategory::active()->get(),
            'tags' => ProductTag::active()->get(),
            'brands' => ProductBrand::active()->get(),
            'attributes' => Attribute::with('terms')->orderBy('sort_order')->get(),
            'variations' => $product->variations()->orderBy('sort_order')->get(),
            'productAttributes' => $product->productAttributes,
            'allProducts' => Product::select('id', 'name', 'sku', 'type', 'price')
                ->where('id', '!=', $product->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validated();

        $validated = $this->applyScheduleStatus($validated);

        $validated = $this->mergeMetaFields($validated);

        // Clean up removed images before updating
        if (isset($validated['images'])) {
            $oldPaths = collect($product->images ?? [])->pluck('path')->filter()->values()->toArray();
            $newPaths = collect($validated['images'])->pluck('path')->filter()->values()->toArray();
            foreach (array_diff($oldPaths, $newPaths) as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        // Clean up old thumbnail file if changed
        if (isset($validated['thumbnail_url']) && $validated['thumbnail_url'] !== $product->thumbnail_url) {
            $oldThumbPath = collect($product->images ?? [])->firstWhere('url', $product->thumbnail_url)['path'] ?? null;
            if ($oldThumbPath) {
                Storage::disk('public')->delete($oldThumbPath);
            }
        }

        $product->update($validated);

        if (isset($validated['categories'])) {
            $product->categories()->sync($validated['categories']);
        }

        if (isset($validated['tags'])) {
            $product->tags()->sync($validated['tags']);
        }

        if (isset($validated['brands'])) {
            $product->brands()->sync($validated['brands']);
        }

        if (isset($validated['product_attributes'])) {
            $this->syncProductAttributes($product, $validated['product_attributes']);
        }

        if (isset($validated['grouped_items'])) {
            $this->syncGroupedItems($product, $validated['grouped_items']);
        }

        if (isset($validated['downloads'])) {
            $this->syncDownloads($product, $validated['downloads']);
        }

        if ($product->wasChanged(['stock_quantity', 'low_stock_threshold', 'track_stock']) && $product->isLowStock()) {
            $this->notifyLowStock($product);
        }

        $this->webhookService->dispatch(
            \Nreljed\ShopModule\Events\WebhookEvents::PRODUCT_UPDATED,
            ['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku],
        );

        return redirect()->route('dashboard.shop.products.index')
            ->with('status', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        $this->webhookService->dispatch(
            \Nreljed\ShopModule\Events\WebhookEvents::PRODUCT_DELETED,
            ['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku],
        );

        return redirect()->route('dashboard.shop.products.index')
            ->with('status', 'Product deleted.');
    }

    public function updateStatus(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,published,future,archived',
        ]);

        $update = ['status' => $validated['status']];

        if ($validated['status'] === 'published') {
            $update['publish_date'] = $product->publish_date ?? now();
        } elseif ($validated['status'] === 'future') {
            $update['publish_date'] = $product->publish_date ?? now()->addDay();
        } elseif ($validated['status'] === 'draft') {
            $update['publish_date'] = null;
        }

        $product->update($update);

        $messages = [
            'published' => 'Product published.',
            'future' => 'Product scheduled.',
            'draft' => 'Product moved to draft.',
            'archived' => 'Product archived.',
        ];

        return redirect()->back()->with('status', $messages[$validated['status']] ?? 'Status updated.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'exists:products,id',
            'action' => 'required|string|in:delete,publish,draft,archive,featured,unfeatured,type,add_category,remove_category,add_tag,remove_tag,stock_status,set_price',
            'type_value' => 'required_if:action,type|in:simple,variable,grouped,external,digital,service',
            'category_ids' => 'required_if:action,add_category,remove_category|array',
            'category_ids.*' => 'exists:product_categories,id',
            'tag_ids' => 'required_if:action,add_tag,remove_tag|array',
            'tag_ids.*' => 'exists:product_tags,id',
            'stock_status_value' => 'required_if:action,stock_status|in:instock,outofstock,onbackorder',
            'price_value' => 'required_if:action,set_price|numeric|min:0',
        ]);

        $ids = $validated['selected_ids'];
        $action = $validated['action'];
        $products = Product::whereIn('id', $ids)->get();

        $count = 0;

        foreach ($products as $product) {
            $canUpdate = $request->user()->can('update', $product);
            $canDelete = $request->user()->can('delete', $product);

            switch ($action) {
                case 'delete':
                    if ($canDelete) {
                        $product->delete();
                        $count++;
                    }
                    break;

                case 'publish':
                case 'draft':
                case 'archive':
                    if ($canUpdate) {
                        $product->update(['status' => $action]);
                        $count++;
                    }
                    break;

                case 'featured':
                    if ($canUpdate) {
                        $product->update(['is_featured' => true]);
                        $count++;
                    }
                    break;

                case 'unfeatured':
                    if ($canUpdate) {
                        $product->update(['is_featured' => false]);
                        $count++;
                    }
                    break;

                case 'type':
                    if ($canUpdate) {
                        $product->update(['type' => $validated['type_value']]);
                        $count++;
                    }
                    break;

                case 'add_category':
                    if ($canUpdate) {
                        $product->categories()->syncWithoutDetaching($validated['category_ids']);
                        $count++;
                    }
                    break;

                case 'remove_category':
                    if ($canUpdate) {
                        $product->categories()->detach($validated['category_ids']);
                        $count++;
                    }
                    break;

                case 'add_tag':
                    if ($canUpdate) {
                        $product->tags()->syncWithoutDetaching($validated['tag_ids']);
                        $count++;
                    }
                    break;

                case 'remove_tag':
                    if ($canUpdate) {
                        $product->tags()->detach($validated['tag_ids']);
                        $count++;
                    }
                    break;

                case 'stock_status':
                    if ($canUpdate) {
                        $product->update(['stock_status' => $validated['stock_status_value']]);
                        $count++;
                    }
                    break;

                case 'set_price':
                    if ($canUpdate) {
                        $product->update(['price' => $validated['price_value']]);
                        $count++;
                    }
                    break;
            }
        }

        $messages = [
            'delete' => "{$count} product(s) deleted.",
            'publish' => "{$count} product(s) published.",
            'draft' => "{$count} product(s) set to draft.",
            'archive' => "{$count} product(s) archived.",
            'featured' => "{$count} product(s) marked as featured.",
            'unfeatured' => "{$count} product(s) unmarked as featured.",
            'type' => "{$count} product(s) type changed.",
            'add_category' => "Categories added to {$count} product(s).",
            'remove_category' => "Categories removed from {$count} product(s).",
            'add_tag' => "Tags added to {$count} product(s).",
            'remove_tag' => "Tags removed from {$count} product(s).",
            'stock_status' => "Stock status updated for {$count} product(s).",
            'set_price' => "Price updated for {$count} product(s).",
        ];

        return redirect()->back()->with('status', $messages[$action] ?? 'Bulk action completed.');
    }

    public function export()
    {
        $this->authorize('viewAny', Product::class);

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['name', 'slug', 'description', 'short_description', 'price', 'compare_price', 'cost_price', 'sku', 'barcode', 'global_unique_id', 'stock_quantity', 'low_stock_threshold', 'track_stock', 'backorders', 'stock_status', 'status', 'type', 'catalog_visibility', 'virtual', 'downloadable', 'external_url', 'button_text', 'is_featured', 'sold_individually', 'menu_order', 'weight', 'categories', 'tags']);

            Product::with('categories', 'tags')->chunk(200, function ($products) use ($handle) {
                foreach ($products as $p) {
                    fputcsv($handle, [
                        $p->name, $p->slug, $p->description, $p->short_description,
                        $p->price, $p->compare_price, $p->cost_price,
                        $p->sku, $p->barcode, $p->global_unique_id, $p->stock_quantity, $p->low_stock_threshold,
                        $p->track_stock ? '1' : '0', $p->backorders ?? 'no', $p->stock_status ?? 'instock',
                        $p->status, $p->type, $p->catalog_visibility,
                        $p->virtual ? '1' : '0', $p->downloadable ? '1' : '0',
                        $p->external_url, $p->button_text,
                        $p->is_featured ? '1' : '0', $p->sold_individually ? '1' : '0',
                        $p->menu_order, $p->weight,
                        $p->categories->pluck('name')->implode('|'),
                        $p->tags->pluck('name')->implode('|'),
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products-'.date('Y-m-d').'.csv"',
        ]);
    }

    public function import(ImportProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $handle = fopen($request->file('file')->getPathname(), 'r');
        fgetcsv($handle); // skip header

        $imported = 0;
        $errors = [];
        $row = 2;

        while (($data = fgetcsv($handle)) !== false) {
            $data = array_map('trim', $data);
            $rowData = [
                'name' => $data[0] ?? '',
                'slug' => $data[1] ?? '',
                'description' => $data[2] ?? '',
                'short_description' => $data[3] ?? '',
                'price' => $data[4] ?? 0,
                'compare_price' => $data[5] ?? null,
                'cost_price' => $data[6] ?? null,
                'sku' => $data[7] ?? '',
                'barcode' => $data[8] ?? '',
                'global_unique_id' => $data[9] ?? '',
                'stock_quantity' => $data[10] ?? 0,
                'low_stock_threshold' => $data[11] ?? null,
                'track_stock' => $data[12] ?? '0',
                'backorders' => $data[13] ?? 'no',
                'stock_status' => $data[14] ?? null,
                'status' => $data[15] ?? 'draft',
                'type' => $data[16] ?? 'simple',
                'catalog_visibility' => $data[17] ?? 'visible',
                'virtual' => $data[18] ?? '0',
                'downloadable' => $data[19] ?? '0',
                'external_url' => $data[20] ?? null,
                'button_text' => $data[21] ?? null,
                'is_featured' => $data[22] ?? '0',
                'sold_individually' => $data[23] ?? '0',
                'menu_order' => $data[24] ?? null,
                'weight' => $data[25] ?? null,
            ];

            $validator = Validator::make($rowData, [
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'sku' => 'nullable|string|max:100|unique:products,sku',
                'status' => 'nullable|string|in:draft,published,archived,future',
                'type' => 'nullable|string|in:simple,variable,grouped,external,digital,service',
                'backorders' => 'nullable|in:no,notify,yes',
                'stock_status' => 'nullable|in:instock,outofstock,onbackorder',
                'catalog_visibility' => 'nullable|in:visible,catalog,search,hidden',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$row}: ".implode(', ', $validator->errors()->all());
                $row++;

                continue;
            }

            $rowData['slug'] = $rowData['slug'] ?: Str::slug($rowData['name']);
            $rowData['price'] = (float) $rowData['price'];
            $rowData['compare_price'] = $rowData['compare_price'] !== '' ? (float) $rowData['compare_price'] : null;
            $rowData['cost_price'] = $rowData['cost_price'] !== '' ? (float) $rowData['cost_price'] : null;
            $rowData['stock_quantity'] = (int) ($rowData['stock_quantity'] ?: 0);
            $rowData['low_stock_threshold'] = $rowData['low_stock_threshold'] !== '' ? (int) $rowData['low_stock_threshold'] : null;
            $rowData['track_stock'] = $rowData['track_stock'] === '1';
            $rowData['backorders'] = in_array($rowData['backorders'] ?? 'no', ['no', 'notify', 'yes']) ? $rowData['backorders'] : 'no';
            $rowData['stock_status'] = in_array($rowData['stock_status'] ?? 'instock', ['instock', 'outofstock', 'onbackorder']) ? $rowData['stock_status'] : null;
            $rowData['virtual'] = ($rowData['virtual'] ?? '0') === '1';
            $rowData['downloadable'] = ($rowData['downloadable'] ?? '0') === '1';
            $rowData['external_url'] = $rowData['external_url'] ?: null;
            $rowData['button_text'] = $rowData['button_text'] ?: null;
            $rowData['is_featured'] = ($rowData['is_featured'] ?? '0') === '1';
            $rowData['sold_individually'] = ($rowData['sold_individually'] ?? '0') === '1';
            $rowData['menu_order'] = $rowData['menu_order'] !== '' ? (int) $rowData['menu_order'] : null;
            $rowData['weight'] = $rowData['weight'] !== '' ? (float) $rowData['weight'] : null;
            $rowData['status'] = $rowData['status'] ?: 'draft';
            $rowData['type'] = $rowData['type'] ?: 'simple';
            $rowData['created_by'] = $request->user()->id;

            Product::create($rowData);
            $imported++;
            $row++;
        }

        fclose($handle);

        $message = "Imported {$imported} products.";
        if (! empty($errors)) {
            $message .= ' Errors: '.implode('; ', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= ' (and '.(count($errors) - 5).' more)';
            }
        }

        return redirect()->route('dashboard.shop.products.index')
            ->with('status', $message);
    }

    public function uploadImage(Request $request)
    {
        Log::info('[GalleryUpload] uploadImage called', [
            'has_file' => $request->hasFile('file'),
            'file_name' => $request->file('file')?->getClientOriginalName(),
            'file_size' => $request->file('file')?->getSize(),
            'file_mime' => $request->file('file')?->getMimeType(),
        ]);

        $profile = UploadProfiles::products($request->user()->id);

        $validated = $request->validate($this->mediaService->rulesFor($profile));

        $result = $this->mediaService->save($validated['file'], $profile);

        $entityId = $request->input('entity_id') ? (int) $request->input('entity_id') : null;
        $this->mediaService->recordMedium(
            $request->user()->id,
            'products',
            $result,
            Product::class,
            $entityId,
        );

        Log::info('[GalleryUpload] uploadImage result', $result);

        return response()->json($result);
    }

    public function deleteImage(Request $request)
    {
        Log::info('[GalleryUpload] deleteImage called', [
            'path_raw' => $request->path,
            'all_input' => $request->all(),
        ]);

        $request->validate([
            'path' => 'required|string',
            'entity_id' => 'required|integer|exists:products,id',
        ]);

        $product = Product::findOrFail($request->entity_id);
        $this->authorize('update', $product);

        $this->mediaService->delete($request->path);

        $images = collect($product->images ?? [])
            ->filter(fn ($img) => ($img['path'] ?? '') !== $request->path)
            ->values()
            ->toArray();
        $product->update(['images' => $images]);

        Log::info('[GalleryUpload] deleteImage success');

        return response()->json(['success' => true]);
    }

    protected function notifyLowStock(Product $product): void
    {
        try {
            $admins = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->get();

            $notification = new AppNotification('low_stock', [
                'title' => 'Low Stock: '.$product->name,
                'message' => $product->name.' ('.$product->stock_quantity.' remaining) is below the low stock threshold of '.$product->low_stock_threshold.'.',
                'module' => 'Shop',
                'link' => route('dashboard.shop.products.edit', $product->id, false),
                'productId' => $product->id,
                'productName' => $product->name,
                'stockQuantity' => $product->stock_quantity,
                'threshold' => $product->low_stock_threshold,
            ]);

            foreach ($admins as $admin) {
                $this->notificationService->notifyViaPreferences($admin, 'low_stock', $notification);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send low stock notification: '.$e->getMessage());
        }
    }

    protected function syncGroupedItems(Product $product, array $items): void
    {
        $syncData = [];
        foreach ($items as $i => $item) {
            $syncData[$item['product_id']] = [
                'quantity' => $item['quantity'] ?? 1,
                'sort_order' => $i,
            ];
        }

        $product->groupedItems()->sync($syncData);
    }

    protected function syncDownloads(Product $product, array $downloads): void
    {
        $keepIds = [];

        foreach ($downloads as $d) {
            $data = [
                'name' => $d['name'],
                'file' => $d['file'],
                'download_limit' => $d['download_limit'] ?? -1,
                'download_expiry' => $d['download_expiry'] ?? 0,
                'sort_order' => $d['sort_order'] ?? 0,
            ];

            if (! empty($d['id'])) {
                $existing = ProductDownload::find($d['id']);
                if ($existing && $existing->product_id === $product->id) {
                    $existing->update($data);
                    $keepIds[] = $existing->id;
                }
            } else {
                $created = $product->downloads()->create($data);
                $keepIds[] = $created->id;
            }
        }

        $product->downloads()->whereNotIn('id', $keepIds)->get()->each(function ($download) {
            Storage::disk('public')->delete($download->file);
            $download->delete();
        });
    }

    protected function applyScheduleStatus(array $validated): array
    {
        $status = $validated['status'] ?? 'draft';

        if ($status === 'publish') {
            $validated['status'] = 'published';
        } elseif ($status === 'future') {
            $publishDate = $validated['publish_date'] ?? null;

            if ($publishDate) {
                $timestamp = strtotime($publishDate);
                if ($timestamp && $timestamp <= time()) {
                    $validated['status'] = 'published';
                    $validated['publish_date'] = null;
                }
            }
        }

        return $validated;
    }

    protected function mergeMetaFields(array $validated): array
    {
        $seo = $validated['seo'] ?? [];

        if (isset($validated['meta_title'])) {
            $seo['meta_title'] = $validated['meta_title'];
            unset($validated['meta_title']);
        }

        if (isset($validated['meta_description'])) {
            $seo['meta_description'] = $validated['meta_description'];
            unset($validated['meta_description']);
        }

        if (isset($validated['og_image'])) {
            $seo['og_image'] = $validated['og_image'];
            unset($validated['og_image']);
        }

        if (isset($validated['focus_keyword'])) {
            $seo['focus_keyword'] = $validated['focus_keyword'];
            unset($validated['focus_keyword']);
        }

        if (isset($validated['seo_metadata'])) {
            $seo['seo_metadata'] = $validated['seo_metadata'];
            unset($validated['seo_metadata']);
        }

        if (! empty($seo)) {
            $validated['seo'] = $seo;
        }

        return $validated;
    }

    protected function syncProductAttributes(Product $product, array $attrs): void
    {
        $keepIds = [];

        foreach ($attrs as $pa) {
            $data = [
                'attribute_id' => $pa['attribute_id'] ?? null,
                'name' => $pa['name'],
                'position' => $pa['position'] ?? 0,
                'visible' => $pa['visible'] ?? true,
                'variation' => $pa['variation'] ?? false,
                'options' => $pa['options'] ?? null,
            ];

            if (! empty($pa['id'])) {
                $existing = ProductAttribute::find($pa['id']);
                if ($existing && $existing->product_id === $product->id) {
                    $existing->update($data);
                    $keepIds[] = $existing->id;
                }
            } else {
                $paModel = $product->productAttributes()->create($data);
                $keepIds[] = $paModel->id;
            }
        }

        $product->productAttributes()->whereNotIn('id', $keepIds)->delete();

        $globalIds = $product->productAttributes()
            ->whereNotNull('attribute_id')
            ->pluck('attribute_id')
            ->unique()
            ->values()
            ->toArray();

        $product->globalAttributes()->sync($globalIds);
    }

    public function adjustStock(AdjustStockRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validated();

        $this->inventoryService->manualAdjust(
            $product,
            $validated['quantity'],
            $validated['reason'],
            $request->user()->id,
        );

        return redirect()->back()->with('status', 'Stock adjusted successfully.');
    }

    public function stockHistory(Request $request, Product $product)
    {
        $this->authorize('view', $product);

        $adjustments = $this->inventoryService->getHistory($product, 50);

        return Inertia::render('shop/products/stockhistory', [
            'product' => $product->only(['id', 'name', 'sku', 'stock_quantity']),
            'adjustments' => $adjustments,
        ]);
    }
}
