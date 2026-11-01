<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * The event a workflow listens for.
 */
enum WorkflowTrigger: string
{
    case ContactCreated = 'contact_created';
    case PostPublished = 'post_published';
    case OrderPlaced = 'order_placed';
    case CartAbandoned = 'cart_abandoned';
    case ProductReviewed = 'product_reviewed';

    /**
     * @return list<array{label: string, value: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['label' => $case->label(), 'value' => $case->value],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::ContactCreated => 'Contact created',
            self::PostPublished => 'Post published',
            self::OrderPlaced => 'Order placed',
            self::CartAbandoned => 'Cart abandoned',
            self::ProductReviewed => 'Product reviewed',
        };
    }

    /**
     * Whether the trigger carries a contact into the action.
     */
    public function isContactEvent(): bool
    {
        return in_array($this, [self::ContactCreated, self::OrderPlaced, self::CartAbandoned], true);
    }
}
