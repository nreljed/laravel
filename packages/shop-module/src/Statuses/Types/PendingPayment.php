<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses\Types;

use Nreljed\ShopModule\Statuses\OrderStatusType;

final class PendingPayment extends OrderStatusType
{
    public function code(): string
    {
        return 'pending_payment';
    }

    public function label(): string
    {
        return 'Pending payment';
    }

    protected function allowedTransitions(): array
    {
        return ['awaiting_fulfilment', 'cancelled', 'failed'];
    }
}
