<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses\Types;

use Nreljed\ShopModule\Statuses\OrderStatusType;

final class Shipped extends OrderStatusType
{
    public function code(): string
    {
        return 'shipped';
    }

    public function label(): string
    {
        return 'Shipped';
    }

    protected function allowedTransitions(): array
    {
        return ['delivered', 'cancelled'];
    }
}
