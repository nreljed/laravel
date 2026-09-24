<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses;

interface OrderStatusContract
{
    public function code(): string;

    public function label(): string;

    public function allowsTransitionTo(OrderStatusContract $next): bool;
}
