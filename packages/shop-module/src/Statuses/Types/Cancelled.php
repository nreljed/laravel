<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses\Types;

use Nreljed\ShopModule\Statuses\OrderStatusType;

final class Cancelled extends OrderStatusType
{
    public function code(): string
    {
        return 'cancelled';
    }

    public function label(): string
    {
        return 'Cancelled';
    }

    protected function allowedTransitions(): array
    {
        return [];
    }
}
