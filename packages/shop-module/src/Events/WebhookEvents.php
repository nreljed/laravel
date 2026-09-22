<?php

namespace Nreljed\ShopModule\Events;

/**
 * Central webhook event catalog (PR 4 / 0.4).
 *
 * The storefront faces these string identifiers only; controllers/services
 * dispatch through `WebhookEvents::*` constants so the catalog stays the
 * single source of truth for what can be subscribed to.
 */
final class WebhookEvents
{
    public const ORDER_PLACED = 'order.placed';

    public const ORDER_UPDATED = 'order.updated';

    public const ORDER_CANCELLED = 'order.cancelled';

    public const ORDER_REFUNDED = 'order.refunded';

    public const ORDER_STATUS_CHANGED = 'order.status_changed';

    public const PRODUCT_CREATED = 'product.created';

    public const PRODUCT_UPDATED = 'product.updated';

    public const PRODUCT_DELETED = 'product.deleted';

    public const CUSTOMER_CREATED = 'customer.created';

    public const CUSTOMER_UPDATED = 'customer.updated';

    public const PRODUCT_STOCK_CHANGED = 'product.stock_changed';

    public const PRODUCT_LOW_STOCK = 'product.low_stock';

    public const WEBHOOK_TEST = 'webhook.test';

    public const SHIPMENT_CREATED = 'shipment.created';

    public const SHIPMENT_SHIPPED = 'shipment.shipped';

    public const SHIPMENT_DELIVERED = 'shipment.delivered';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ORDER_PLACED,
            self::ORDER_UPDATED,
            self::ORDER_CANCELLED,
            self::ORDER_REFUNDED,
            self::ORDER_STATUS_CHANGED,
            self::PRODUCT_CREATED,
            self::PRODUCT_UPDATED,
            self::PRODUCT_DELETED,
            self::CUSTOMER_CREATED,
            self::CUSTOMER_UPDATED,
            self::PRODUCT_STOCK_CHANGED,
            self::PRODUCT_LOW_STOCK,
            self::WEBHOOK_TEST,
            self::SHIPMENT_CREATED,
            self::SHIPMENT_SHIPPED,
            self::SHIPMENT_DELIVERED,
        ];
    }
}
