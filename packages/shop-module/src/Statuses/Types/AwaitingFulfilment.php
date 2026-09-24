<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses\Types;

use Nreljed\ShopModule\Statuses\OrderStatusType;

final class AwaitingFulfilment extends OrderStatusType
{
    public function code(): string
    {
        return 'awaiting_fulfilment';
    }

    public function label(): string
    {
        return 'Awaiting fulfilment';
    }

    protected function allowedTransitions(): array
    {
        return ['processing', 'cancelled', 'failed'];
    }
}
