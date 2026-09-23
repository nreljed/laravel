<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Pipeline\Pipeline;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;
use Nreljed\ShopModule\Pipelines\Cart\PersistLine;
use Nreljed\ShopModule\Pipelines\Cart\ResolveUnitPrice;
use Nreljed\ShopModule\Pipelines\Cart\ValidatePurchaseQuantity;
use Nreljed\ShopModule\Pipelines\Cart\ValidateVariant;
use Nreljed\ShopModule\Pipelines\CartContext;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\InventoryService;
use PHPUnit\Framework\TestCase;
use PDO;

final class CartPipelineGoldenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $resolver = new ConnectionResolver;
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
        $resolver->addConnection('default', $connection);
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);

        $connection->setSchemaGrammar(new SQLiteGrammar($connection));

        (new SchemaBuilder($connection))->create('cart_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('cart_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variation_id')->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->string('currency', 3)->nullable();
            $table->string('variant_label')->nullable();
            $table->timestamps();
        });
    }

    public function test_variant_not_belonging_to_product_short_circuits(): void
    {
        $spy = $this->spy();

        $result = (new Pipeline)
            ->send($this->context(variation: $this->variation(['product_id' => 9])))
            ->through([new ValidateVariant, $spy])
            ->thenReturn();

        $this->assertSame('Variant does not belong to this product.', $result->error);
        $this->assertFalse($spy->called);
    }

    public function test_invisible_variant_is_rejected(): void
    {
        $this->assertSame(
            'This variant is not available for purchase.',
            $this->runVariant($this->variation(['visible' => false]))->error
        );
    }

    public function test_variant_inheriting_parent_stock_requires_parent_stock(): void
    {
        $this->assertSame(
            'This variant is out of stock.',
            $this->runVariant(
                $this->variation(['manage_stock' => 'parent']),
                $this->product(['track_stock' => true, 'stock_quantity' => 0, 'backorders' => 'no'])
            )->error
        );
    }

    public function test_variant_managing_own_stock_rejected_when_exhausted(): void
    {
        $this->assertSame(
            'This variant is out of stock.',
            $this->runVariant($this->variation(['manage_stock' => 'true', 'stock_quantity' => 0, 'backorders' => 'no']))->error
        );
    }

    public function test_simple_product_out_of_stock_is_rejected(): void
    {
        $this->assertSame(
            'Product is out of stock.',
            $this->runVariant(null, $this->product(['track_stock' => true, 'stock_quantity' => 0, 'backorders' => 'no']))->error
        );
    }

    public function test_valid_variation_passes_validation(): void
    {
        $spy = $this->spy();

        $result = (new Pipeline)
            ->send($this->context(variation: $this->variation()))
            ->through([new ValidateVariant, $spy])
            ->thenReturn();

        $this->assertNull($result->error);
        $this->assertTrue($spy->called);
    }

    public function test_unit_price_resolved_from_variation_effective_price(): void
    {
        $context = $this->context(variation: $this->variation(['active_price' => 42.5]));

        $result = (new Pipeline)->send($context)->through([new ResolveUnitPrice])->thenReturn();

        $this->assertSame(42.5, $result->unitPrice);
    }

    public function test_unit_price_falls_back_to_variation_price_when_active_price_unset(): void
    {
        $context = $this->context(variation: $this->variation(['active_price' => null, 'price' => 12.0]));

        $result = (new Pipeline)->send($context)->through([new ResolveUnitPrice])->thenReturn();

        $this->assertSame(12.0, $result->unitPrice);
    }

    public function test_unit_price_falls_back_to_product_price_without_variation(): void
    {
        $context = $this->context();

        $result = (new Pipeline)->send($context)->through([new ResolveUnitPrice])->thenReturn();

        $this->assertSame(10.0, $result->unitPrice);
    }

    public function test_sold_individually_quantity_exceeded_is_rejected(): void
    {
        $spy = $this->spy();

        $result = (new Pipeline)
            ->send($this->context(
                product: $this->product(['sold_individually' => true]),
                totalQuantity: 2,
            ))
            ->through([new ValidatePurchaseQuantity($this->inventoryFake(true)), $spy])
            ->thenReturn();

        $this->assertSame('This product can only be purchased one at a time.', $result->error);
        $this->assertFalse($spy->called);
    }

    public function test_exceeding_stock_is_rejected(): void
    {
        $result = (new Pipeline)
            ->send($this->context(totalQuantity: 5))
            ->through([new ValidatePurchaseQuantity($this->inventoryFake(false))])
            ->thenReturn();

        $this->assertSame('Requested quantity exceeds available stock.', $result->error);
    }

    public function test_quantity_within_stock_passes(): void
    {
        $result = (new Pipeline)
            ->send($this->context(totalQuantity: 1))
            ->through([new ValidatePurchaseQuantity($this->inventoryFake(true))])
            ->thenReturn();

        $this->assertNull($result->error);
    }

    public function test_persist_line_updates_existing_item_quantity_only(): void
    {
        $cart = new Cart;
        $cart->id = 10;

        $existing = $cart->items()->create([
            'product_id' => 1,
            'quantity' => 3,
            'unit_price' => 20.0,
            'currency' => 'EUR',
            'variant_label' => 'L',
        ]);

        $context = new CartContext(
            cart: $cart,
            product: $this->product(),
            variation: null,
            quantity: 4,
            existingItem: $existing,
            variantLabel: 'L',
            totalQuantity: 7,
            unitPrice: 20.0,
        );

        $result = (new Pipeline)->send($context)->through([new PersistLine($this->currencyFake())])->thenReturn();

        $this->assertNull($result->error);
        $existing->refresh();
        $this->assertSame(7, $existing->quantity);
        $this->assertSame('20.00', $existing->unit_price);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_persist_line_creates_new_item_from_context(): void
    {
        $cart = new Cart;
        $cart->id = 10;

        $context = new CartContext(
            cart: $cart,
            product: $this->product(),
            variation: $this->variation(),
            quantity: 2,
            existingItem: null,
            variantLabel: 'XL',
            totalQuantity: 2,
            unitPrice: 42.5,
        );

        $result = (new Pipeline)->send($context)->through([new PersistLine($this->currencyFake())])->thenReturn();

        $this->assertNull($result->error);

        $item = $cart->items()->first();
        $this->assertSame(10, $item->cart_id);
        $this->assertSame(1, $item->product_id);
        $this->assertSame(2, $item->product_variation_id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('42.50', $item->unit_price);
        $this->assertSame('EUR', $item->currency);
        $this->assertSame('XL', $item->variant_label);
    }

    public function test_full_cart_chain_behaves_end_to_end(): void
    {
        $cart = new Cart;
        $cart->id = 10;

        $context = new CartContext(
            cart: $cart,
            product: $this->product(),
            variation: $this->variation(['active_price' => 42.5]),
            quantity: 2,
            existingItem: null,
            variantLabel: 'XL',
            totalQuantity: 2,
        );

        $result = (new Pipeline)
            ->send($context)
            ->through([
                new ValidateVariant,
                new ResolveUnitPrice,
                new ValidatePurchaseQuantity($this->inventoryFake(true)),
                new PersistLine($this->currencyFake()),
            ])
            ->thenReturn();

        $this->assertNull($result->error);
        $this->assertSame(42.5, $result->unitPrice);

        $item = $cart->items()->first();
        $this->assertSame(2, $item->product_variation_id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('42.50', $item->unit_price);
        $this->assertSame('XL', $item->variant_label);
    }

    private function runVariant(?ProductVariation $variation, ?Product $product = null): CartContext
    {
        return (new Pipeline)
            ->send($this->context(variation: $variation, product: $product))
            ->through([new ValidateVariant])
            ->thenReturn();
    }

    private function context(?Product $product = null, ?ProductVariation $variation = null, int $quantity = 1, int $totalQuantity = 1): CartContext
    {
        return new CartContext(
            cart: new Cart,
            product: $product ?? $this->product(),
            variation: $variation,
            quantity: $quantity,
            existingItem: null,
            variantLabel: null,
            totalQuantity: $totalQuantity,
        );
    }

    private function product(array $attrs = []): Product
    {
        $product = new Product(array_merge(['name' => 'Test Product', 'price' => 10.0], $attrs));
        $product->id = 1;

        return $product;
    }

    private function variation(array $attrs = []): ProductVariation
    {
        $variation = new ProductVariation(array_merge([
            'product_id' => 1,
            'price' => 12.0,
            'active_price' => 12.0,
            'manage_stock' => 'parent',
            'backorders' => 'no',
            'stock_quantity' => 10,
            'visible' => true,
        ], $attrs));
        $variation->id = 2;

        return $variation;
    }

    private function inventoryFake(bool $result): InventoryService
    {
        return new class($result) extends InventoryService
        {
            public function __construct(private bool $result) {}

            public function validateStock(Product $product, int $quantity, ?ProductVariation $variation = null): bool
            {
                return $this->result;
            }
        };
    }

    private function currencyFake(): CurrencyService
    {
        return new class extends CurrencyService
        {
            public function __construct() {}

            public function defaultCode(): string
            {
                return 'EUR';
            }
        };
    }

    private function spy(): object
    {
        return new class
        {
            public bool $called = false;

            public function handle($passable, $next)
            {
                $this->called = true;

                return $next($passable);
            }
        };
    }
}