<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses;

use InvalidArgumentException;
use Nreljed\ShopModule\Statuses\Types\AwaitingFulfilment;
use Nreljed\ShopModule\Statuses\Types\Cancelled;
use Nreljed\ShopModule\Statuses\Types\Completed;
use Nreljed\ShopModule\Statuses\Types\Delivered;
use Nreljed\ShopModule\Statuses\Types\Failed;
use Nreljed\ShopModule\Statuses\Types\Packed;
use Nreljed\ShopModule\Statuses\Types\PendingPayment;
use Nreljed\ShopModule\Statuses\Types\Processing;
use Nreljed\ShopModule\Statuses\Types\Refunded;
use Nreljed\ShopModule\Statuses\Types\Shipped;

final class OrderStatusManager
{
    /** @var array<string, OrderStatusContract> */
    protected array $statuses = [];

    /**
     * @param  array<int, OrderStatusContract>  $statuses
     */
    public function __construct(array $statuses = [])
    {
        foreach ($statuses as $status) {
            $this->register($status);
        }
    }

    public static function defaults(): OrderStatusManager
    {
        return new self([
            new PendingPayment,
            new Processing,
            new Packed,
            new Shipped,
            new Delivered,
            new Completed,
            new Cancelled,
            new Failed,
            new AwaitingFulfilment,
            new Refunded,
        ]);
    }

    public function register(OrderStatusContract $status): void
    {
        $this->statuses[$status->code()] = $status;
    }

    public function driver(string $code): ?OrderStatusContract
    {
        return $this->statuses[$code] ?? null;
    }

    /**
     * @return array<string, OrderStatusContract>
     */
    public function all(): array
    {
        return $this->statuses;
    }

    public function canTransition(string $from, string $to): bool
    {
        $source = $this->driver($from);
        $target = $this->driver($to);

        return $source !== null && $target !== null && $source->allowsTransitionTo($target);
    }
}
