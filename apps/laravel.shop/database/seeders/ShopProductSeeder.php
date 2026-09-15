<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Concerns\BootstrapsAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\AttributeTerm;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductAttribute;
use Nreljed\ShopModule\Models\ProductBrand;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\ProductDownload;
use Nreljed\ShopModule\Models\ProductReview;
use Nreljed\ShopModule\Models\ProductTag;
use Nreljed\ShopModule\Models\ProductVariation;

class ShopProductSeeder extends Seeder
{
    use BootstrapsAdmin;

    public function run(): void
    {
        $admin = $this->ensureAdminUser();
        $adminId = $admin->id;

        $brands = $this->createBrands();
        $categories = $this->createCategories();
        $tags = $this->createTags();
        $attributeData = $this->createAttributes();

        $this->createProducts($adminId, $brands, $categories, $tags, $attributeData);
        $this->createReviews($adminId);
    }

    private function createBrands(): array
    {
        $data = [
            ['name' => 'Xiaomi', 'description' => 'Xiaomi - innovative technology for everyone', 'sort_order' => 1, 'is_active' => true],
            ['name' => 'Redmi', 'description' => 'Redmi - quality smartphones at honest prices', 'sort_order' => 2, 'is_active' => true],
        ];

        $ids = [];
        foreach ($data as $item) {
            $brand = ProductBrand::firstOrCreate(['slug' => Str::slug($item['name'])], $item);
            $ids[$brand->slug] = $brand->id;
        }

        return $ids;
    }

    private function createCategories(): array
    {
        $data = [
            ['name' => 'Electronics', 'slug' => 'electronics', 'parent_slug' => null, 'sort_order' => 1],
            ['name' => 'Smartphones', 'slug' => 'smartphones', 'parent_slug' => 'electronics', 'sort_order' => 2],
            ['name' => 'Accessories', 'slug' => 'accessories', 'parent_slug' => 'electronics', 'sort_order' => 3],
            ['name' => 'Wearables', 'slug' => 'wearables', 'parent_slug' => null, 'sort_order' => 4],
            ['name' => 'Digital Goods', 'slug' => 'digital-goods', 'parent_slug' => null, 'sort_order' => 5],
            ['name' => 'Services', 'slug' => 'services', 'parent_slug' => null, 'sort_order' => 6],
        ];

        $ids = [];
        foreach ($data as $item) {
            $parentId = $item['parent_slug'] ? ($ids[$item['parent_slug']] ?? null) : null;
            $category = ProductCategory::firstOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'description' => null,
                    'parent_id' => $parentId,
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ]
            );
            $ids[$item['slug']] = $category->id;
        }

        return $ids;
    }

    private function createTags(): array
    {
        $data = [
            ['name' => '5G', 'slug' => '5g', 'sort_order' => 1],
            ['name' => 'Flagship', 'slug' => 'flagship', 'sort_order' => 2],
            ['name' => 'Best Seller', 'slug' => 'best-seller', 'sort_order' => 3],
            ['name' => 'Budget', 'slug' => 'budget', 'sort_order' => 4],
            ['name' => 'Gaming', 'slug' => 'gaming', 'sort_order' => 5],
            ['name' => 'New Arrival', 'slug' => 'new-arrival', 'sort_order' => 6],
        ];

        $ids = [];
        foreach ($data as $item) {
            $tag = ProductTag::firstOrCreate(['slug' => $item['slug']], $item);
            $ids[$tag->slug] = $tag->id;
        }

        return $ids;
    }

    private function createAttributes(): array
    {
        $colorAttr = Attribute::firstOrCreate(
            ['slug' => 'color'],
            ['name' => 'Color', 'display_type' => 'color', 'sort_order' => 1, 'is_active' => true]
        );

        $storageAttr = Attribute::firstOrCreate(
            ['slug' => 'storage'],
            ['name' => 'Storage', 'display_type' => 'button', 'sort_order' => 2, 'is_active' => true]
        );

        $colorTerms = [
            ['name' => 'Midnight Black', 'slug' => 'midnight-black', 'swatch_value' => '#000000', 'sort_order' => 1],
            ['name' => 'Pearl White', 'slug' => 'pearl-white', 'swatch_value' => '#F5F5F5', 'sort_order' => 2],
            ['name' => 'Forest Green', 'slug' => 'forest-green', 'swatch_value' => '#228B22', 'sort_order' => 3],
        ];

        $storageTerms = [
            ['name' => '128GB', 'slug' => '128gb', 'sort_order' => 1],
            ['name' => '256GB', 'slug' => '256gb', 'sort_order' => 2],
            ['name' => '512GB', 'slug' => '512gb', 'sort_order' => 3],
        ];

        $termIds = [];

        foreach ($colorTerms as $item) {
            $term = AttributeTerm::firstOrCreate(
                ['attribute_id' => $colorAttr->id, 'slug' => $item['slug']],
                ['name' => $item['name'], 'swatch_value' => $item['swatch_value'], 'sort_order' => $item['sort_order']]
            );
            $termIds['color_'.$item['slug']] = $term->id;
        }

        foreach ($storageTerms as $item) {
            $term = AttributeTerm::firstOrCreate(
                ['attribute_id' => $storageAttr->id, 'slug' => $item['slug']],
                ['name' => $item['name'], 'sort_order' => $item['sort_order']]
            );
            $termIds['storage_'.$item['slug']] = $term->id;
        }

        return [
            'color' => $colorAttr,
            'storage' => $storageAttr,
            'terms' => $termIds,
        ];
    }

    private function createProducts(int $adminId, array $brands, array $categories, array $tags, array $attributeData): void
    {
        $now = Carbon::now();

        $products = [
            // 1. Xiaomi 14 Pro - simple product
            [
                'slug' => 'xiaomi-14-pro',
                'data' => [
                    'name' => 'Xiaomi 14 Pro',
                    'type' => 'simple',
                    'price' => 899.00,
                    'compare_price' => 999.00,
                    'cost_price' => 720.00,
                    'sku' => 'XM-14PRO-EU',
                    'barcode' => '6941812705122',
                    'global_unique_id' => '6941812705122',
                    'vendor' => 'Xiaomi',
                    'stock_quantity' => 50,
                    'incoming_stock' => 20,
                    'low_stock_threshold' => 5,
                    'track_stock' => true,
                    'backorders' => 'no',
                    'status' => 'published',
                    'catalog_visibility' => 'visible',
                    'is_featured' => true,
                    'is_taxable' => true,
                    'weight' => 0.22,
                    'length' => 16.3,
                    'width' => 7.5,
                    'height' => 0.8,
                    'menu_order' => 1,
                    'description' => 'Experience the pinnacle of smartphone innovation with the Xiaomi 14 Pro. Featuring a stunning LTPO AMOLED display, the cutting-edge Snapdragon 8 Gen 3 processor, and a professional Leica optics camera system with a 50MP triple setup. The 120W HyperCharge technology takes you from 0 to 100% in just 18 minutes, while the 5000mAh battery keeps you going all day. IP68 water resistance, MIUI 15 based on Android 15, and a premium ceramic build make this the ultimate flagship experience.',
                    'short_description' => 'Snapdragon 8 Gen 3 | Leica 50MP Triple Camera | 120W HyperCharge | 6.73" LTPO AMOLED | IP68',
                    'seo' => [
                        'meta_title' => 'Xiaomi 14 Pro 5G - Flagship Smartphone | Shop App',
                        'meta_description' => 'Shop the Xiaomi 14 Pro with Leica optics, Snapdragon 8 Gen 3, 120W HyperCharge, and LTPO AMOLED display. Free shipping across Europe.',
                        'og_image' => 'https://picsum.photos/seed/xiaomi-14-pro-og/1200/630',
                    ],
                    'thumbnail_url' => 'https://picsum.photos/seed/xiaomi-14-pro/640/640',
                    'images' => [
                        ['url' => 'https://picsum.photos/seed/xiaomi-14-pro-1/640/640', 'path' => null],
                        ['url' => 'https://picsum.photos/seed/xiaomi-14-pro-2/640/640', 'path' => null],
                        ['url' => 'https://picsum.photos/seed/xiaomi-14-pro-3/640/640', 'path' => null],
                    ],
                    'brands' => ['xiaomi'],
                    'categories' => ['smartphones'],
                    'tags' => ['5g', 'flagship', 'best-seller', 'new-arrival'],
                ],
            ],

            // 2. Redmi Note 13 Pro - variable product (color + storage variations)
            [
                'slug' => 'redmi-note-13-pro',
                'data' => [
                    'name' => 'Redmi Note 13 Pro',
                    'type' => 'variable',
                    'price' => 0,
                    'sku' => 'RN13PRO-BASE',
                    'vendor' => 'Redmi',
                    'stock_quantity' => 0,
                    'track_stock' => false,
                    'status' => 'published',
                    'catalog_visibility' => 'visible',
                    'is_featured' => true,
                    'is_taxable' => true,
                    'weight' => 0.19,
                    'length' => 16.1,
                    'width' => 7.5,
                    'height' => 0.8,
                    'menu_order' => 2,
                    'description' => 'The Redmi Note 13 Pro redefines mid-range excellence with a 200MP main camera, 6.67" AMOLED 120Hz display, and MediaTek Dimensity 7200 Ultra chipset. With 67W turbo charging and a 5100mAh battery, this phone keeps up with your busy lifestyle. The sleek glass sandwich design with IP54 splash resistance offers premium feel at an accessible price. Choose from three stunning colors and two storage configurations.',
                    'short_description' => '200MP Camera | 6.67" AMOLED 120Hz | Dimensity 7200 Ultra | 67W Turbo Charging | 5100mAh',
                    'seo' => [
                        'meta_title' => 'Redmi Note 13 Pro 5G - 200MP Camera | Shop App',
                        'meta_description' => 'Discover the Redmi Note 13 Pro with 200MP camera, AMOLED 120Hz display, and 67W turbo charging at unbeatable prices.',
                        'og_image' => 'https://picsum.photos/seed/redmi-note-13-pro-og/1200/630',
                    ],
                    'thumbnail_url' => 'https://picsum.photos/seed/redmi-note-13-pro/640/640',
                    'images' => [
                        ['url' => 'https://picsum.photos/seed/redmi-note-13-pro-1/640/640', 'path' => null],
                        ['url' => 'https://picsum.photos/seed/redmi-note-13-pro-2/640/640', 'path' => null],
                    ],
                    'brands' => ['redmi', 'xiaomi'],
                    'categories' => ['smartphones'],
                    'tags' => ['5g', 'best-seller'],
                ],
            ],

            // 3. Xiaomi 67W Turbo Charger - simple accessory (also grouped child)
            [
                'slug' => 'xiaomi-67w-turbo-charger',
                'data' => [
                    'name' => 'Xiaomi 67W Turbo Charger',
                    'type' => 'simple',
                    'price' => 29.00,
                    'compare_price' => 39.00,
                    'sku' => 'XM-67WCH-EU',
                    'vendor' => 'Xiaomi',
                    'stock_quantity' => 200,
                    'incoming_stock' => 100,
                    'low_stock_threshold' => 10,
                    'track_stock' => true,
                    'backorders' => 'notify',
                    'status' => 'published',
                    'catalog_visibility' => 'visible',
                    'is_featured' => false,
                    'is_taxable' => true,
                    'weight' => 0.10,
                    'length' => 5.5,
                    'width' => 5.5,
                    'height' => 2.8,
                    'menu_order' => 3,
                    'description' => 'Charge your compatible Xiaomi devices at blazing speeds with the official Xiaomi 67W Turbo Charger. Featuring GaN technology for compact size, USB-C output, and intelligent temperature control. Compatible with Xiaomi 14 series, Redmi Note 13 series, and other USB-C devices. Supports QC 3.0 and PD 3.0 fast charging protocols.',
                    'short_description' => 'GaN Technology | USB-C | QC 3.0 / PD 3.0 | Intelligent Temperature Control | Compact Design',
                    'seo' => [
                        'meta_title' => 'Xiaomi 67W Turbo Charger - GaN Fast Charger | Shop App',
                        'meta_description' => 'Buy the official Xiaomi 67W Turbo Charger with GaN technology. Fast charging for Xiaomi smartphones and USB-C devices.',
                        'og_image' => 'https://picsum.photos/seed/xiaomi-charger-og/1200/630',
                    ],
                    'thumbnail_url' => 'https://picsum.photos/seed/xiaomi-67w-charger/640/640',
                    'images' => [
                        ['url' => 'https://picsum.photos/seed/xiaomi-67w-charger-1/640/640', 'path' => null],
                    ],
                    'brands' => ['xiaomi'],
                    'categories' => ['accessories'],
                    'tags' => ['best-seller'],
                ],
            ],

            // 4. Xiaomi 20000mAh Power Bank 3 - simple accessory (also grouped child)
            [
                'slug' => 'xiaomi-20000mah-power-bank-3',
                'data' => [
                    'name' => 'Xiaomi 20000mAh Power Bank 3',
                    'type' => 'simple',
                    'price' => 39.00,
                    'sku' => 'XM-PB20000-3',
                    'vendor' => 'Xiaomi',
                    'stock_quantity' => 150,
                    'low_stock_threshold' => 10,
                    'track_stock' => true,
                    'backorders' => 'yes',
                    'status' => 'published',
                    'catalog_visibility' => 'visible',
                    'is_featured' => false,
                    'is_taxable' => true,
                    'weight' => 0.35,
                    'length' => 15.0,
                    'width' => 7.2,
                    'height' => 2.5,
                    'menu_order' => 4,
                    'description' => 'The Xiaomi 20000mAh Power Bank 3 offers massive capacity in a sleek metal body. Dual USB-A and single USB-C port with 18W fast charging support. Charge three devices simultaneously. LED indicator shows remaining power. 20000mAh capacity charges most smartphones 4-5 times. Features circuit protection against overcharge, over-discharge, and short circuit.',
                    'short_description' => '20000mAh | 3-Port (2xUSB-A + USB-C) | 18W Fast Charge | Metal Body | Triple Device Charging',
                    'seo' => [
                        'meta_title' => 'Xiaomi 20000mAh Power Bank 3 - Fast Charging | Shop App',
                        'meta_description' => 'Shop the Xiaomi 20000mAh Power Bank 3 with triple-port fast charging, metal body, and high-density lithium polymer cells.',
                        'og_image' => 'https://picsum.photos/seed/xiaomi-powerbank-og/1200/630',
                    ],
                    'thumbnail_url' => 'https://picsum.photos/seed/xiaomi-powerbank/640/640',
                    'images' => [
                        ['url' => 'https://picsum.photos/seed/xiaomi-powerbank-1/640/640', 'path' => null],
                        ['url' => 'https://picsum.photos/seed/xiaomi-powerbank-2/640/640', 'path' => null],
                    ],
                    'brands' => ['xiaomi'],
                    'categories' => ['accessories'],
                    'tags' => ['gaming'],
                ],
            ],

            // 5. Xiaomi Smart Band 8 Pro - external/affiliate product
            [
                'slug' => 'xiaomi-smart-band-8-pro',
                'data' => [
                    'name' => 'Xiaomi Smart Band 8 Pro',
                    'type' => 'external',
                    'price' => 79.00,
                    'compare_price' => 99.00,
                    'sku' => 'XM-BAND8PRO-GL',
                    'vendor' => 'Xiaomi',
                    'stock_quantity' => 0,
                    'track_stock' => false,
                    'status' => 'published',
                    'catalog_visibility' => 'visible',
                    'is_featured' => false,
                    'is_taxable' => true,
                    'virtual' => true,
                    'external_url' => 'https://www.mi.com/global/product/xiaomi-smart-band-8-pro/',
                    'button_text' => 'Buy on mi.com',
                    'weight' => null,
                    'length' => null,
                    'width' => null,
                    'height' => null,
                    'menu_order' => 5,
                    'description' => 'The Xiaomi Smart Band 8 Pro features a stunning 1.74" AMOLED always-on display with 60Hz refresh rate. Track your fitness with 150+ workout modes, built-in GPS, heart rate monitoring, SpO2 tracking, and sleep analysis. With 5ATM water resistance and up to 14 days battery life, it is your perfect fitness companion. This product is sold directly by Xiaomi via mi.com.',
                    'short_description' => '1.74" AMOLED Display | Built-in GPS | 150+ Workout Modes | 5ATM Water Resistant | 14-Day Battery',
                    'seo' => [
                        'meta_title' => 'Xiaomi Smart Band 8 Pro - Fitness Tracker | Shop App',
                        'meta_description' => 'Discover the Xiaomi Smart Band 8 Pro with AMOLED display, GPS, 150+ workout modes. Available via mi.com affiliate link.',
                        'og_image' => 'https://picsum.photos/seed/xiaomi-band8pro-og/1200/630',
                    ],
                    'thumbnail_url' => 'https://picsum.photos/seed/xiaomi-band8pro/640/640',
                    'images' => [
                        ['url' => 'https://picsum.photos/seed/xiaomi-band8pro-1/640/640', 'path' => null],
                        ['url' => 'https://picsum.photos/seed/xiaomi-band8pro-2/640/640', 'path' => null],
                    ],
                    'brands' => ['xiaomi'],
                    'categories' => ['wearables'],
                    'tags' => ['new-arrival', 'gaming'],
                ],
            ],

            // 6. Xiaomi Smart Life Bundle - grouped product
            [
                'slug' => 'xiaomi-smart-life-bundle',
                'data' => [
                    'name' => 'Xiaomi Smart Life Bundle',
                    'type' => 'grouped',
                    'price' => 0,
                    'sku' => 'XM-BUNDLE-SLB1',
                    'vendor' => 'Xiaomi',
                    'stock_quantity' => 0,
                    'track_stock' => false,
                    'status' => 'published',
                    'catalog_visibility' => 'visible',
                    'is_featured' => false,
                    'is_taxable' => true,
                    'virtual' => false,
                    'weight' => null,
                    'length' => null,
                    'width' => null,
                    'height' => null,
                    'menu_order' => 6,
                    'description' => 'The Xiaomi Smart Life Bundle brings together the essential Xiaomi accessories for the complete ecosystem experience. Includes the 67W Turbo Charger for ultra-fast charging and the 20000mAh Power Bank for all-day power on the go. Save when you buy them together in this specially priced bundle.',
                    'short_description' => 'Includes: 67W Turbo Charger + 20000mAh Power Bank 3 | Save €19',
                    'seo' => [
                        'meta_title' => 'Xiaomi Smart Life Bundle - Charger + Power Bank | Shop App',
                        'meta_description' => 'Save on the Xiaomi Smart Life Bundle featuring the 67W Turbo Charger and 20000mAh Power Bank 3. Essential Xiaomi accessories.',
                        'og_image' => 'https://picsum.photos/seed/xiaomi-bundle-og/1200/630',
                    ],
                    'thumbnail_url' => 'https://picsum.photos/seed/xiaomi-bundle/640/640',
                    'images' => [
                        ['url' => 'https://picsum.photos/seed/xiaomi-bundle-1/640/640', 'path' => null],
                    ],
                    'brands' => ['xiaomi'],
                    'categories' => ['accessories'],
                    'tags' => ['best-seller'],
                ],
            ],

            // 7. Xiaomi Pro Themes Pack - digital/downloadable product
            [
                'slug' => 'xiaomi-pro-themes-pack',
                'data' => [
                    'name' => 'Xiaomi Pro Themes Pack',
                    'type' => 'digital',
                    'price' => 19.00,
                    'compare_price' => 29.00,
                    'sku' => 'XM-THEMES-PRO',
                    'vendor' => 'Xiaomi',
                    'stock_quantity' => 0,
                    'track_stock' => false,
                    'status' => 'published',
                    'catalog_visibility' => 'catalog',
                    'is_featured' => false,
                    'is_taxable' => false,
                    'virtual' => true,
                    'downloadable' => true,
                    'weight' => null,
                    'length' => null,
                    'width' => null,
                    'height' => null,
                    'menu_order' => 7,
                    'description' => 'Transform your Xiaomi device with the Pro Themes Pack featuring 50 exclusive premium themes, 100+ custom icons, 20 animated wallpapers, and 10 always-on display designs. Created by top MIUI designers. Compatible with all Xiaomi and Redmi devices running MIUI 14 and above. Instant download after purchase with unlimited access.',
                    'short_description' => '50 Premium Themes | 100+ Icons | 20 Animated Wallpapers | 10 AOD Designs | MIUI 14+',
                    'seo' => [
                        'meta_title' => 'Xiaomi Pro Themes Pack - 50 Premium Themes | Shop App',
                        'meta_description' => 'Download the Xiaomi Pro Themes Pack with 50 premium themes, icons, and wallpapers for your Xiaomi device. Instant digital delivery.',
                        'og_image' => 'https://picsum.photos/seed/xiaomi-themes-og/1200/630',
                    ],
                    'thumbnail_url' => 'https://picsum.photos/seed/xiaomi-themes/640/640',
                    'images' => [
                        ['url' => 'https://picsum.photos/seed/xiaomi-themes-1/640/640', 'path' => null],
                        ['url' => 'https://picsum.photos/seed/xiaomi-themes-2/640/640', 'path' => null],
                    ],
                    'brands' => ['xiaomi'],
                    'categories' => ['digital-goods'],
                    'tags' => [],
                ],
            ],

            // 8. Xiaomi Priority Care - service product
            [
                'slug' => 'xiaomi-priority-care',
                'data' => [
                    'name' => 'Xiaomi Priority Care (1 Year)',
                    'type' => 'service',
                    'price' => 49.00,
                    'sku' => 'XM-CARE-1YR',
                    'vendor' => 'Xiaomi',
                    'stock_quantity' => 0,
                    'track_stock' => false,
                    'status' => 'published',
                    'catalog_visibility' => 'visible',
                    'is_featured' => false,
                    'is_taxable' => false,
                    'virtual' => true,
                    'sold_individually' => true,
                    'weight' => null,
                    'length' => null,
                    'width' => null,
                    'height' => null,
                    'menu_order' => 8,
                    'description' => 'Extend your peace of mind with Xiaomi Priority Care, our comprehensive 1-year service plan. Enjoy priority customer support with dedicated hotline, express repair service at authorized centers (48-hour turnaround), one free screen replacement, 20% discount on out-of-warranty repairs, and battery replacement at 50% off. Coverage extends to all Xiaomi and Redmi smartphones purchased from our store.',
                    'short_description' => 'Priority Support | Express Repair | 1 Free Screen Replacement | 50% Battery Replacement',
                    'seo' => [
                        'meta_title' => 'Xiaomi Priority Care 1-Year Service Plan | Shop App',
                        'meta_description' => 'Protect your Xiaomi device with Priority Care. Priority support, express repair, free screen replacement, and exclusive discounts.',
                        'og_image' => 'https://picsum.photos/seed/xiaomi-care-og/1200/630',
                    ],
                    'thumbnail_url' => 'https://picsum.photos/seed/xiaomi-care/640/640',
                    'images' => [
                        ['url' => 'https://picsum.photos/seed/xiaomi-care-1/640/640', 'path' => null],
                    ],
                    'brands' => ['xiaomi'],
                    'categories' => ['services'],
                    'tags' => ['new-arrival'],
                ],
            ],
        ];

        $createdProducts = [];

        foreach ($products as $item) {
            $data = $item['data'];
            $slug = $item['slug'];

            $brandSlugs = $data['brands'] ?? [];
            $categorySlugs = $data['categories'] ?? [];
            $tagSlugs = $data['tags'] ?? [];

            unset($data['brands'], $data['categories'], $data['tags']);

            $data['created_by'] = $adminId;
            $data['stock_status'] = $this->calculateStockStatus($data);

            $product = Product::firstOrCreate(['slug' => $slug], $data);

            if (! $product->wasRecentlyCreated) {
                $product->update($data);
                $product->save();
            }

            if (! empty($brandSlugs)) {
                $product->brands()->sync(array_map(fn ($s) => $brands[$s], $brandSlugs));
            }

            if (! empty($categorySlugs)) {
                $product->categories()->sync(array_map(fn ($s) => $categories[$s], $categorySlugs));
            }

            if (! empty($tagSlugs)) {
                $product->tags()->sync(array_map(fn ($s) => $tags[$s], $tagSlugs));
            }

            $createdProducts[$slug] = $product;
        }

        // Attach grouped items to the bundle (Xiaomi Smart Life Bundle)
        $bundle = $createdProducts['xiaomi-smart-life-bundle'] ?? null;
        $charger = $createdProducts['xiaomi-67w-turbo-charger'] ?? null;
        $powerbank = $createdProducts['xiaomi-20000mah-power-bank-3'] ?? null;

        if ($bundle && $charger && $powerbank) {
            $bundle->groupedItems()->sync([
                $charger->id => ['quantity' => 1, 'sort_order' => 1],
                $powerbank->id => ['quantity' => 1, 'sort_order' => 2],
            ]);
        }

        // Set up variable product attributes and variations for Redmi Note 13 Pro
        $redmiNote = $createdProducts['redmi-note-13-pro'] ?? null;

        if ($redmiNote && $attributeData['color'] && $attributeData['storage']) {
            $redmiNote->globalAttributes()->sync([
                $attributeData['color']->id,
                $attributeData['storage']->id,
            ]);

            ProductAttribute::firstOrCreate(
                [
                    'product_id' => $redmiNote->id,
                    'attribute_id' => $attributeData['color']->id,
                ],
                [
                    'name' => 'Color',
                    'position' => 0,
                    'visible' => true,
                    'variation' => true,
                    'options' => ['Midnight Black', 'Pearl White', 'Forest Green'],
                ]
            );

            ProductAttribute::firstOrCreate(
                [
                    'product_id' => $redmiNote->id,
                    'attribute_id' => $attributeData['storage']->id,
                ],
                [
                    'name' => 'Storage',
                    'position' => 1,
                    'visible' => true,
                    'variation' => true,
                    'options' => ['128GB', '256GB', '512GB'],
                ]
            );

            $terms = $attributeData['terms'];

            $variations = [
                [
                    'sku' => 'RN13PRO-BLK-128',
                    'price' => 399.00,
                    'sale_price' => 349.00,
                    'sale_from' => $now->copy()->subDay(),
                    'sale_to' => $now->copy()->addDays(30),
                    'stock_quantity' => 25,
                    'manage_stock' => 'true',
                    'sort_order' => 1,
                    'image_url' => 'https://picsum.photos/seed/rn13pro-black/640/640',
                    'attributes' => [
                        ['attribute_id' => $attributeData['color']->id, 'attribute_term_id' => $terms['color_midnight-black'], 'name' => 'Midnight Black', 'slug' => 'midnight-black'],
                        ['attribute_id' => $attributeData['storage']->id, 'attribute_term_id' => $terms['storage_128gb'], 'name' => '128GB', 'slug' => '128gb'],
                    ],
                ],
                [
                    'sku' => 'RN13PRO-BLK-256',
                    'price' => 449.00,
                    'sale_price' => 399.00,
                    'sale_from' => $now->copy()->subDay(),
                    'sale_to' => $now->copy()->addDays(30),
                    'stock_quantity' => 15,
                    'manage_stock' => 'true',
                    'sort_order' => 2,
                    'image_url' => 'https://picsum.photos/seed/rn13pro-black/640/640',
                    'attributes' => [
                        ['attribute_id' => $attributeData['color']->id, 'attribute_term_id' => $terms['color_midnight-black'], 'name' => 'Midnight Black', 'slug' => 'midnight-black'],
                        ['attribute_id' => $attributeData['storage']->id, 'attribute_term_id' => $terms['storage_256gb'], 'name' => '256GB', 'slug' => '256gb'],
                    ],
                ],
                [
                    'sku' => 'RN13PRO-WHT-128',
                    'price' => 399.00,
                    'sale_price' => 349.00,
                    'sale_from' => $now->copy()->subDay(),
                    'sale_to' => $now->copy()->addDays(30),
                    'stock_quantity' => 30,
                    'manage_stock' => 'true',
                    'sort_order' => 3,
                    'image_url' => 'https://picsum.photos/seed/rn13pro-white/640/640',
                    'attributes' => [
                        ['attribute_id' => $attributeData['color']->id, 'attribute_term_id' => $terms['color_pearl-white'], 'name' => 'Pearl White', 'slug' => 'pearl-white'],
                        ['attribute_id' => $attributeData['storage']->id, 'attribute_term_id' => $terms['storage_128gb'], 'name' => '128GB', 'slug' => '128gb'],
                    ],
                ],
                [
                    'sku' => 'RN13PRO-WHT-256',
                    'price' => 449.00,
                    'sale_price' => 399.00,
                    'sale_from' => $now->copy()->subDay(),
                    'sale_to' => $now->copy()->addDays(30),
                    'stock_quantity' => 10,
                    'manage_stock' => 'true',
                    'sort_order' => 4,
                    'image_url' => 'https://picsum.photos/seed/rn13pro-white/640/640',
                    'attributes' => [
                        ['attribute_id' => $attributeData['color']->id, 'attribute_term_id' => $terms['color_pearl-white'], 'name' => 'Pearl White', 'slug' => 'pearl-white'],
                        ['attribute_id' => $attributeData['storage']->id, 'attribute_term_id' => $terms['storage_256gb'], 'name' => '256GB', 'slug' => '256gb'],
                    ],
                ],
                [
                    'sku' => 'RN13PRO-GRN-128',
                    'price' => 409.00,
                    'sale_price' => 359.00,
                    'sale_from' => $now->copy()->subDay(),
                    'sale_to' => $now->copy()->addDays(30),
                    'stock_quantity' => 20,
                    'manage_stock' => 'true',
                    'sort_order' => 5,
                    'image_url' => 'https://picsum.photos/seed/rn13pro-green/640/640',
                    'attributes' => [
                        ['attribute_id' => $attributeData['color']->id, 'attribute_term_id' => $terms['color_forest-green'], 'name' => 'Forest Green', 'slug' => 'forest-green'],
                        ['attribute_id' => $attributeData['storage']->id, 'attribute_term_id' => $terms['storage_128gb'], 'name' => '128GB', 'slug' => '128gb'],
                    ],
                ],
                [
                    'sku' => 'RN13PRO-GRN-256',
                    'price' => 459.00,
                    'sale_price' => 409.00,
                    'sale_from' => $now->copy()->subDay(),
                    'sale_to' => $now->copy()->addDays(30),
                    'stock_quantity' => 5,
                    'manage_stock' => 'true',
                    'sort_order' => 6,
                    'image_url' => 'https://picsum.photos/seed/rn13pro-green/640/640',
                    'attributes' => [
                        ['attribute_id' => $attributeData['color']->id, 'attribute_term_id' => $terms['color_forest-green'], 'name' => 'Forest Green', 'slug' => 'forest-green'],
                        ['attribute_id' => $attributeData['storage']->id, 'attribute_term_id' => $terms['storage_256gb'], 'name' => '256GB', 'slug' => '256gb'],
                    ],
                ],
            ];

            foreach ($variations as $varData) {
                $attrs = $varData['attributes'];
                unset($varData['attributes']);

                $varData['product_id'] = $redmiNote->id;

                $variation = ProductVariation::firstOrCreate(
                    ['sku' => $varData['sku']],
                    $varData
                );

                if (! $variation->wasRecentlyCreated) {
                    $variation->update($varData);
                }

                $variation->syncAttributes($attrs);
            }
        }

        // Create downloads for the digital product (Xiaomi Pro Themes Pack)
        $themesPack = $createdProducts['xiaomi-pro-themes-pack'] ?? null;

        if ($themesPack) {
            $downloads = [
                [
                    'name' => 'Xiaomi Pro Themes Pack v1.0',
                    'file' => 'downloads/xiaomi-pro-themes-v1.zip',
                    'download_limit' => 3,
                    'download_expiry' => 365,
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Xiaomi Wallpaper Collection',
                    'file' => 'downloads/xiaomi-wallpapers-hd.zip',
                    'download_limit' => -1,
                    'download_expiry' => null,
                    'sort_order' => 2,
                ],
            ];

            foreach ($downloads as $dlData) {
                $dlData['product_id'] = $themesPack->id;
                ProductDownload::firstOrCreate(
                    ['product_id' => $themesPack->id, 'name' => $dlData['name']],
                    $dlData
                );
            }
        }
    }

    private function createReviews(int $adminId): void
    {
        // Remove legacy review users (renamed in the 2026-07 rework)
        User::where('email', 'like', '%@review.example.com')
            ->where('email', 'not like', 'olivia.carter@review.example.com')
            ->where('email', 'not like', 'noah.mitchell@review.example.com')
            ->where('email', 'not like', 'ava.rodriguez@review.example.com')
            ->delete();

        $reviewUsers = [];
        foreach ([
            ['name' => 'Olivia Carter', 'email' => 'olivia.carter@review.example.com'],
            ['name' => 'Noah Mitchell', 'email' => 'noah.mitchell@review.example.com'],
            ['name' => 'Ava Rodriguez', 'email' => 'ava.rodriguez@review.example.com'],
        ] as $data) {
            [$first, $last] = array_pad(explode(' ', $data['name'], 2), 2, '');

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'first_name' => $first,
                    'last_name' => $last,
                    'display_name' => $data['name'],
                    'username' => strtolower($first).'.'.strtolower($last),
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                    'phone' => '+1 '.rand(200, 999).' '.rand(200, 999).' '.str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT),
                    'contact_email' => $data['email'],
                    'bio' => "{$data['name']} is a verified reviewer sharing hands-on experience with our products.",
                    'social_links' => [
                        'twitter' => 'https://twitter.com/'.strtolower($first),
                        'facebook' => null,
                        'instagram' => null,
                        'linkedin' => 'https://linkedin.com/in/'.strtolower($first).'-'.strtolower($last),
                        'tiktok' => null,
                        'youtube' => null,
                        'whatsapp' => null,
                    ],
                    'avatar' => $this->downloadAvatar(strtolower($first).'-'.strtolower($last)),
                ]
            );

            $user->assignRole(config('roles.default_role', 'customer'));
            $reviewUsers[] = $user->id;
        }

        $allUserIds = array_merge([$adminId], $reviewUsers);

        $xiaomi14 = Product::where('slug', 'xiaomi-14-pro')->first();
        $redmiNote = Product::where('slug', 'redmi-note-13-pro')->first();

        $reviews = [];

        if ($xiaomi14) {
            $reviews[] = [
                'product_id' => $xiaomi14->id,
                'user_id' => $allUserIds[0],
                'rating' => 5,
                'title' => 'Absolutely incredible phone',
                'content' => 'The Xiaomi 14 Pro is hands down the best smartphone I have ever owned. The Leica camera system produces stunning photos, the display is gorgeous, and the charging speed is mind-blowing. Worth every penny.',
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => $adminId,
            ];

            $reviews[] = [
                'product_id' => $xiaomi14->id,
                'user_id' => $allUserIds[1],
                'rating' => 4,
                'title' => 'Great phone, minor MIUI quirks',
                'content' => 'Excellent hardware and camera. The Snapdragon 8 Gen 3 flies through everything. MIUI still has some pre-installed apps but overall a fantastic flagship experience.',
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => $adminId,
            ];

            $reviews[] = [
                'product_id' => $xiaomi14->id,
                'user_id' => $allUserIds[2],
                'rating' => 3,
                'title' => 'Good but not a huge upgrade from 13 Pro',
                'content' => 'Solid phone with great cameras, but if you have the 13 Pro, the upgrade is minimal. The 120W charging is the main differentiator.',
                'is_approved' => false,
                'approved_at' => null,
                'approved_by' => null,
            ];
        }

        if ($redmiNote) {
            $reviews[] = [
                'product_id' => $redmiNote->id,
                'user_id' => $allUserIds[1],
                'rating' => 5,
                'title' => 'Best mid-range phone of the year',
                'content' => 'The 200MP camera is genuinely impressive for this price point. The AMOLED display is vibrant and smooth. Battery life easily lasts two days. Cannot recommend this enough.',
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => $adminId,
            ];
        }

        foreach ($reviews as $reviewData) {
            ProductReview::firstOrCreate(
                [
                    'product_id' => $reviewData['product_id'],
                    'user_id' => $reviewData['user_id'],
                ],
                $reviewData
            );
        }
    }

    private function calculateStockStatus(array $data): ?string
    {
        if (! ($data['track_stock'] ?? true)) {
            return null;
        }

        $qty = $data['stock_quantity'] ?? 0;

        if ($qty > 0) {
            return 'instock';
        }

        $backorders = $data['backorders'] ?? 'no';

        if ($backorders !== 'no') {
            return 'onbackorder';
        }

        return 'outofstock';
    }

    private function downloadAvatar(string $seed): ?string
    {
        try {
            $response = Http::timeout(15)->get("https://picsum.photos/seed/{$seed}/200/200");

            if (! $response->successful()) {
                return null;
            }

            $filename = $seed.'-'.Str::random(8).'.jpg';

            Storage::disk('avatars')->put($filename, $response->body());

            return $filename;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
