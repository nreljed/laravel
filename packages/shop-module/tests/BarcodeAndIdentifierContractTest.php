<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;
use Nreljed\ShopModule\Tests\Concerns\BootsShopSchema;
use PHPUnit\Framework\TestCase;

/**
 * The barcode / GTIN / MPN identifier columns (PR 11C).
 *
 * Products had `barcode` and `global_unique_id` but no `mpn`, and a variation
 * had `barcode` but neither `global_unique_id` nor `mpn` - the whole set a
 * product carries beyond its SKU was not available on variations. This pins
 * that a product stores an `mpn` and a variation stores GTIN and MPN.
 */
final class BarcodeAndIdentifierContractTest extends TestCase
{
    use BootsShopSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootShopSchema();
        $this->resetShopTables();
    }

    public function test_the_products_table_has_an_mpn_column(): void
    {
        $product = $this->product(['mpn' => '991-000985']);

        $this->assertRowExists('products', [
            'id' => $product->id,
            'mpn' => '991-000985',
        ], 'A product must be able to store its manufacturer part number.');
    }

    public function test_the_variations_table_stores_barcode_global_id_and_mpn(): void
    {
        $product = $this->product();

        $variation = ProductVariation::create([
            'product_id' => $product->id,
            'sku' => 'VAR-1',
            'barcode' => '9780262510875',
            'global_unique_id' => 'GTIN-14',
            'mpn' => 'MPN-VAR-1',
            'price' => 12.5,
        ]);

        $this->assertRowExists('product_variations', [
            'id' => $variation->id,
            'barcode' => '9780262510875',
            'global_unique_id' => 'GTIN-14',
            'mpn' => 'MPN-VAR-1',
        ], 'Saving a variation with identifiers set must not fail - the columns being absent was a silent editor killer.');
    }

    public function test_updating_a_variation_keeps_its_identifiers(): void
    {
        $product = $this->product();
        $variation = ProductVariation::create([
            'product_id' => $product->id,
            'sku' => 'VAR-1',
            'barcode' => 'old-barcode',
            'global_unique_id' => null,
            'mpn' => null,
        ]);

        $variation->update([
            'barcode' => 'new-barcode',
            'global_unique_id' => 'GTIN-8',
            'mpn' => 'MPN-2',
        ]);

        $this->assertRowExists('product_variations', [
            'id' => $variation->id,
            'barcode' => 'new-barcode',
            'global_unique_id' => 'GTIN-8',
            'mpn' => 'MPN-2',
        ]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Thing',
            'slug' => 'thing-'.uniqid(),
            'status' => 'published',
            'type' => 'simple',
            'catalog_visibility' => 'visible',
            'price' => 10,
            'stock_quantity' => 5,
            'track_stock' => true,
        ]);
    }
}