<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Pipelines\Cart\PersistLine;
use Nreljed\ShopModule\Pipelines\Cart\ValidateProductAvailability;
use Nreljed\ShopModule\Pipelines\Cart\ResolveUnitPrice;
use Nreljed\ShopModule\Pipelines\Cart\ValidatePurchaseQuantity;
use Nreljed\ShopModule\Pipelines\Cart\ValidateVariant;
use Nreljed\ShopModule\Pipelines\CartContext;
use Nreljed\ShopModule\Pipelines\CartLineContext;
use Nreljed\ShopModule\Pipelines\CartLines\BuildOrderItemAttributes;
use Nreljed\ShopModule\Pipelines\Checkout\BuildQuote;
use Nreljed\ShopModule\Pipelines\Checkout\ClearCart;
use Nreljed\ShopModule\Pipelines\Checkout\CreateOrder;
use Nreljed\ShopModule\Pipelines\Checkout\CreateOrderItems;
use Nreljed\ShopModule\Pipelines\Checkout\RecordCouponUsage;
use Nreljed\ShopModule\Pipelines\Checkout\RecordInventory;
use Nreljed\ShopModule\Pipelines\Checkout\ResolveShippingMethod;
use Nreljed\ShopModule\Pipelines\Checkout\ValidateAvailability;
use Nreljed\ShopModule\Pipelines\Checkout\ValidateStock;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Services\CheckoutService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class PipelinesContractTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require dirname(__DIR__).'/config/shop.php';
    }

    public function test_pipelines_config_defines_three_ordered_pipelines(): void
    {
        $this->assertSame([
            'cart' => [
                ValidateProductAvailability::class,
                ValidateVariant::class,
                ResolveUnitPrice::class,
                ValidatePurchaseQuantity::class,
                PersistLine::class,
            ],
            'cart_lines' => [
                BuildOrderItemAttributes::class,
            ],
            'checkout' => [
                ValidateAvailability::class,
                ValidateStock::class,
                ResolveShippingMethod::class,
                BuildQuote::class,
                CreateOrder::class,
                RecordCouponUsage::class,
                CreateOrderItems::class,
                RecordInventory::class,
                ClearCart::class,
            ],
        ], $this->config['pipelines']);
    }

    public function test_every_configured_pipe_class_exists_with_handle(): void
    {
        foreach ($this->config['pipelines'] as $pipeline => $pipes) {
            foreach ($pipes as $pipe) {
                $this->assertTrue(class_exists($pipe), "{$pipeline} pipe {$pipe} is missing");
                $this->assertTrue(method_exists($pipe, 'handle'), "{$pipe} is missing handle()");
            }
        }
    }

    public function test_cart_context_contract(): void
    {
        $params = (new ReflectionClass(CartContext::class))->getConstructor()?->getParameters() ?? [];

        $this->assertSame([
            'cart', 'product', 'variation', 'quantity', 'existingItem', 'variantLabel', 'totalQuantity', 'unitPrice', 'error',
        ], array_map(fn ($p) => $p->getName(), $params));
    }

    public function test_cart_line_context_contract(): void
    {
        $params = (new ReflectionClass(CartLineContext::class))->getConstructor()?->getParameters() ?? [];

        $this->assertSame([
            'item', 'order', 'currency', 'attributes',
        ], array_map(fn ($p) => $p->getName(), $params));
    }

    public function test_checkout_context_contract(): void
    {
        $class = new ReflectionClass(CheckoutContext::class);

        $params = $class->getConstructor()?->getParameters() ?? [];

        $this->assertSame([
            'cart', 'validated', 'user', 'provisionalOrder',
        ], array_map(fn ($p) => $p->getName(), $params));

        foreach (['shippingMethod', 'quote', 'order'] as $property) {
            $this->assertTrue($class->hasProperty($property), "CheckoutContext::\${$property} is missing");
            $this->assertTrue($class->getProperty($property)->isPublic(), "CheckoutContext::\${$property} must be public");
        }
    }

    public function test_checkout_service_is_stateless_orchestrator(): void
    {
        $service = new ReflectionClass(CheckoutService::class);

        $this->assertNull($service->getConstructor(), 'CheckoutService must have no constructor dependencies');

        $method = new ReflectionMethod(CheckoutService::class, 'processCheckout');
        $params = $method->getParameters();

        $this->assertSame(['cart', 'validated', 'user', 'provisionalOrder'], array_map(fn ($p) => $p->getName(), $params));
        $this->assertSame(Order::class, (string) $method->getReturnType());
        $this->assertNull($params[3]->getDefaultValue());
    }
}