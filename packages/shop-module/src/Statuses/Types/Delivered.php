<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses\Types;

use Nreljed\ShopModule\Statuses\OrderStatusType;

final class Delivered extends OrderStatusType
{
    public function code(): string
    {
        return 'delivered';
    }

    public function label(): string
    {
        return 'Delivered';
    }

    protected function allowedTransitions(): array
    {
        return ['completed', 'refunded'];
    }
}
