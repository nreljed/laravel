<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses;

abstract class OrderStatusType implements OrderStatusContract
{
    final public function allowsTransitionTo(OrderStatusContract $next): bool
    {
        return in_array($next->code(), $this->allowedTransitions(), true);
    }

    abstract public function code(): string;

    abstract public function label(): string;

    /**
     * @return array<int, string>
     */
    abstract protected function allowedTransitions(): array;
}
