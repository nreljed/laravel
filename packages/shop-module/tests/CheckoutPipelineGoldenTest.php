<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Pipelines\CartLineContext;
use Nreljed\ShopModule\Pipelines\CartLines\BuildOrderItemAttributes;
use Nreljed\ShopModule\Pipelines\Checkout\BuildQuote;
use Nreljed\ShopModule\Pipelines\Checkout\RecordCouponUsage;
use Nreljed\ShopModule\Pipelines\Checkout\ValidateStock;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Services\CouponService;
use Nreljed\ShopModule\Services\InventoryService;
use Nreljed\ShopModule\Services\QuoteService;
use PHPUnit\Framework\TestCase;

final class CheckoutPipelineGoldenTest extends TestCase
{
    public function test_validate_stock_throws_localized_validation_exception(): void
    {
        $this->harness();

        $product = new Product(['name' => 'Seiko Watch', 'track_stock' => true, 'stock_quantity' => 0, 'backorders' => 'no']);
        $item = new CartItem(['product_id' => 1, 'quantity' => 1]);
        $item->setRelation('product', $product);
        $item->setRelation('variation', null);

        $cart = (new Cart)->setRelation('items', new Collection([$item]));

        $service = new ValidateStock(new class extends InventoryService
        {
            public function __construct() {}

            public function validateStock(Product $product, int $quantity, ?ProductVariation $variation = null): bool
            {
                return false;
            }
        });

        $context = new CheckoutContext($cart, [], null);

        try {
            $service->handle($context, fn ($ctx) => $ctx);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('Seiko Watch is out of stock or insufficient quantity.', $e->errors()['stock'][0]);
        }
    }

    public function test_validate_stock_passes_when_all_items_in_stock(): void
    {
        $this->harness();

        $product = new Product(['name' => 'In Stock', 'track_stock' => false, 'stock_status' => 'instock']);
        $item = new CartItem(['product_id' => 1, 'quantity' => 2]);
        $item->setRelation('product', $product);
        $item->setRelation('variation', null);

        $cart = (new Cart)->setRelation('items', new Collection([$item]));

        $service = new ValidateStock(new class extends InventoryService
        {
            public function __construct() {}

            public function validateStock(Product $product, int $quantity, ?ProductVariation $variation = null): bool
            {
                return true;
            }
        });

        $called = false;
        $service->handle(new CheckoutContext($cart, [], null), function ($ctx) use (&$called) {
            $called = true;

            return $ctx;
        });

        $this->assertTrue($called);
    }

    public function test_build_quote_passes_effective_context_to_quote_service(): void
    {
        $this->harness();

        $cart = new Cart;
        $method = new ShippingMethod;
        $method->id = 3;

        $quotes = new class extends QuoteService
        {
            public array $received = [];

            public function __construct() {}

            public function quote(Cart $cart, ?ShippingMethod $shippingMethod = null, ?array $billingAddress = null, ?array $shippingAddress = null, ?int $userId = null): array
            {
                $this->received = [$cart, $shippingMethod, $billingAddress, $shippingAddress, $userId];

                return ['currency' => 'EUR', 'coupons' => new Collection, 'discount_breakdown' => []];
            }
        };

        $context = new CheckoutContext($cart, [
            'shipping_method_id' => 3,
            'billing_address' => ['country' => 'GB'],
            'shipping_address' => null,
        ], null);
        $context->shippingMethod = $method;

        (new Pipeline)->send($context)->through([new BuildQuote($quotes)])->thenReturn();

        $this->assertSame($cart, $quotes->received[0]);
        $this->assertSame($method, $quotes->received[1]);
        $this->assertSame(['country' => 'GB'], $quotes->received[2]);
        $this->assertNull($quotes->received[3]);
        $this->assertNull($quotes->received[4]);
        $this->assertSame('EUR', $context->quote['currency']);
    }

    public function test_record_coupon_usage_walks_quote_coupons_in_order(): void
    {
        $this->harness();

        $order = new Order;
        $order->id = 7;

        $coupons = new class extends CouponService
        {
            public array $calls = [];

            public function __construct() {}

            public function recordUsage(Coupon $coupon, int $orderId, ?int $userId, float $discountAmount): void
            {
                $this->calls[] = [$coupon->id, $orderId, $userId, $discountAmount];
            }
        };

        $context = new CheckoutContext(new Cart, [], null);
        $context->order = $order;
        $context->quote = [
            'coupons' => new Collection([$this->coupon(1, 'A'), $this->coupon(2, 'B')]),
            'discount_breakdown' => [
                ['coupon_id' => 1, 'discount' => 4.0],
                ['coupon_id' => 2, 'discount' => 2.5],
            ],
        ];

        (new Pipeline)->send($context)->through([new RecordCouponUsage($coupons)])->thenReturn();

        $this->assertSame([[1, 7, null, 4.0], [2, 7, null, 2.5]], $coupons->calls);
    }

    public function test_record_coupon_usage_defaults_missing_breakdown_to_zero(): void
    {
        $this->harness();

        $order = new Order;
        $order->id = 9;

        $coupons = new class extends CouponService
        {
            public array $calls = [];

            public function __construct() {}

            public function recordUsage(Coupon $coupon, int $orderId, ?int $userId, float $discountAmount): void
            {
                $this->calls[] = [$coupon->id, $orderId, $userId, $discountAmount];
            }
        };

        $context = new CheckoutContext(new Cart, [], null);
        $context->order = $order;
        $context->quote = [
            'coupons' => new Collection([$this->coupon(1, 'A')]),
            'discount_breakdown' => [],
        ];

        (new Pipeline)->send($context)->through([new RecordCouponUsage($coupons)])->thenReturn();

        $this->assertSame([[1, 9, null, 0.0]], $coupons->calls);
    }

    public function test_build_order_item_attributes_without_variation(): void
    {
        $product = new Product(['name' => 'Pro', 'sku' => 'SKU-1', 'thumbnail_url' => 'th.jpg']);
        $product->id = 1;

        $item = new CartItem([
            'product_id' => 1,
            'product_variation_id' => null,
            'quantity' => 2,
            'unit_price' => 50.0,
            'variant_label' => null,
        ]);
        $item->setRelation('product', $product);
        $item->setRelation('variation', null);

        $order = new Order;
        $order->id = 5;

        $context = new CartLineContext($item, $order, 'EUR');

        (new Pipeline)->send($context)->through([new BuildOrderItemAttributes])->thenReturn();

        $this->assertSame([
            'order_id' => 5,
            'product_id' => 1,
            'product_variation_id' => null,
            'product_name' => 'Pro',
            'product_sku' => 'SKU-1',
            'unit_price' => '50.00',
            'quantity' => 2,
            'currency' => 'EUR',
            'subtotal' => 100.0,
            'total' => 100.0,
            'variant_label' => null,
            'image_url' => 'th.jpg',
        ], $context->attributes);
    }

    public function test_build_order_item_attributes_prefers_variation_fields(): void
    {
        $product = new Product(['name' => 'Pro', 'sku' => 'SKU-1', 'thumbnail_url' => 'th.jpg']);
        $product->id = 1;

        $variation = new ProductVariation(['sku' => 'VAR-9', 'image_url' => 'v.jpg']);
        $variation->id = 4;

        $item = new CartItem([
            'product_id' => 1,
            'product_variation_id' => 4,
            'quantity' => 1,
            'unit_price' => 60.0,
            'variant_label' => 'Red',
        ]);
        $item->setRelation('product', $product);
        $item->setRelation('variation', $variation);

        $order = new Order;
        $order->id = 6;

        $context = new CartLineContext($item, $order, 'TND');

        (new Pipeline)->send($context)->through([new BuildOrderItemAttributes])->thenReturn();

        $this->assertSame([
            'order_id' => 6,
            'product_id' => 1,
            'product_variation_id' => 4,
            'product_name' => 'Pro',
            'product_sku' => 'VAR-9',
            'unit_price' => '60.00',
            'quantity' => 1,
            'currency' => 'TND',
            'subtotal' => 60.0,
            'total' => 60.0,
            'variant_label' => 'Red',
            'image_url' => 'v.jpg',
        ], $context->attributes);
    }

    private function harness(): void
    {
        $container = new Container;
        Container::setInstance($container);
        Facade::setFacadeApplication($container);

        $loader = new ArrayLoader;
        $loader->addMessages('en', '*', [
            ':name is out of stock or insufficient quantity.' => ':name is out of stock or insufficient quantity.',
        ]);

        $translator = new Translator($loader, 'en');
        $container->bind('translator', fn () => $translator);
        $container->bind('validator', fn () => new Factory($translator));
    }

    private function coupon(int $id, string $code): Coupon
    {
        $coupon = new Coupon;
        $coupon->id = $id;
        $coupon->code = $code;

        return $coupon;
    }
}
