<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses\Types;

use Nreljed\ShopModule\Statuses\OrderStatusType;

final class Refunded extends OrderStatusType
{
    public function code(): string
    {
        return 'refunded';
    }

    public function label(): string
    {
        return 'Refunded';
    }

    protected function allowedTransitions(): array
    {
        return [];
    }
}
