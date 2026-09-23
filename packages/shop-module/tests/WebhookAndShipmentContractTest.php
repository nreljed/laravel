<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SQLiteConnection;
use Nreljed\ShopModule\Events\WebhookEvents;
use Nreljed\ShopModule\Jobs\SendWebhook;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Shipment;
use Nreljed\ShopModule\Models\ShipmentItem;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Public-contract tests for PR 4 (queued webhooks + shipments). No database
 * queries and no app()/config() — matches the DB-free convention of
 * PriceResolverTest. An in-memory connection resolver is registered only so
 * Eloquent can build relation objects (no query ever runs).
 */
final class WebhookAndShipmentContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $resolver = new ConnectionResolver;
        $resolver->addConnection('default', new SQLiteConnection(new PDO('sqlite::memory:')));
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);
    }
    public function test_event_catalog_is_unique(): void
    {
        $events = WebhookEvents::all();

        $this->assertSame($events, array_values(array_unique($events)));
    }

    public function test_event_catalog_covers_order_lifecycle(): void
    {
        $events = WebhookEvents::all();

        foreach ([
            'order.placed',
            'order.updated',
            'order.cancelled',
            'order.refunded',
            'order.status_changed',
        ] as $expected) {
            $this->assertContains($expected, $events, "Missing order lifecycle event: {$expected}");
        }
    }

    public function test_event_catalog_covers_product_customer_inventory_and_shipment(): void
    {
        $events = WebhookEvents::all();

        foreach ([
            'product.created',
            'product.updated',
            'product.deleted',
            'customer.created',
            'customer.updated',
            'product.stock_changed',
            'product.low_stock',
            'webhook.test',
            'shipment.created',
            'shipment.shipped',
            'shipment.delivered',
        ] as $expected) {
            $this->assertContains($expected, $events, "Missing event: {$expected}");
        }
    }

    public function test_send_webhook_job_is_queued_unique_and_configured(): void
    {
        $job = new SendWebhook(42, 7, retryAttempts: 3, retryDelay: 60);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('webhook-call-42', $job->uniqueId());
        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->backoff);
    }

    public function test_send_webhook_job_clamps_minimum_tries_and_backoff(): void
    {
        $job = new SendWebhook(1, 1, retryAttempts: 0, retryDelay: 0);

        $this->assertSame(1, $job->tries);
        $this->assertSame(1, $job->backoff);
    }

    public function test_shipment_model_exposes_shipment_relations(): void
    {
        $shipment = new Shipment(['id' => 1]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $shipment->order());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $shipment->items());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $shipment->creator());
    }

    public function test_shipment_status_helpers(): void
    {
        $pending = new Shipment(['status' => 'pending']);
        $shipped = new Shipment(['status' => 'shipped']);
        $delivered = new Shipment(['status' => 'delivered']);
        $cancelled = new Shipment(['status' => 'cancelled']);

        $this->assertFalse($pending->isShipped());
        $this->assertTrue($shipped->isShipped());
        $this->assertTrue($delivered->isShipped());
        $this->assertFalse($cancelled->isShipped());
        $this->assertFalse($shipped->isDelivered());
        $this->assertTrue($delivered->isDelivered());
    }

    public function test_shipment_item_exposes_shipment_and_order_item_relations(): void
    {
        $item = new ShipmentItem(['id' => 1]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $item->shipment());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $item->orderItem());
    }

    public function test_order_exposes_shipments_relation(): void
    {
        $order = new Order(['id' => 1]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $order->shipments());
    }
}