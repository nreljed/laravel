<?php

namespace Nreljed\ShopModule\Services;

use InvalidArgumentException;
use Nreljed\ShopModule\Models\Order;

class OrderStatusService
{
    const array STATUSES = [
        'draft', 'pending', 'on-hold', 'confirmed', 'processing',
        'completed', 'cancelled', 'refunded', 'failed', 'trash',
    ];

    const array TRANSITIONS = [
        'draft' => ['pending', 'cancelled'],
        'pending' => ['confirmed', 'on-hold', 'cancelled', 'failed'],
        'on-hold' => ['pending', 'confirmed', 'cancelled'],
        'confirmed' => ['processing', 'on-hold', 'cancelled'],
        'processing' => ['completed', 'on-hold', 'cancelled'],
        'completed' => ['refunded', 'cancelled'],
        'cancelled' => ['pending', 'trash'],
        'refunded' => [],
        'failed' => ['pending', 'cancelled'],
        'trash' => ['pending'],
    ];

    public function getAllowedTransitions(string $currentStatus): array
    {
        return self::TRANSITIONS[$currentStatus] ?? [];
    }

    public function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, $this->getAllowedTransitions($from), true);
    }

    public function validateTransition(Order $order, string $newStatus): void
    {
        if (! in_array($newStatus, self::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$newStatus}");
        }

        if (! $this->canTransition($order->status, $newStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition from '{$order->status}' to '{$newStatus}'."
            );
        }
    }
}
