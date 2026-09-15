<?php

namespace Nreljed\ShopModule\Services;

use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderAction;

class OrderActionService
{
    public function log(
        Order $order,
        string $actionType,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?int $userId = null,
        ?string $notes = null,
    ): OrderAction {
        return $order->actions()->create([
            'action_type' => $actionType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id' => $userId ?? auth()->id(),
            'notes' => $notes,
        ]);
    }

    public function statusChanged(Order $order, string $oldStatus, string $newStatus, ?int $userId = null): OrderAction
    {
        return $this->log($order, 'status_changed', $oldStatus, $newStatus, $userId);
    }

    public function paymentReceived(Order $order, ?int $userId = null): OrderAction
    {
        return $this->log($order, 'payment_received', 'pending', 'paid', $userId);
    }

    public function refunded(Order $order, float $amount, ?string $reason = null, ?int $userId = null, ?array $items = null): OrderAction
    {
        $notes = $reason;
        if ($items) {
            $count = count($items);
            $notes = ($reason ? $reason.' | ' : '')."{$count} item(s) refunded";
        }

        return $this->log($order, 'refunded', null, number_format($amount, 2), $userId, $notes);
    }

    public function created(Order $order, ?int $userId = null): OrderAction
    {
        return $this->log($order, 'created', null, $order->order_number, $userId);
    }

    public function edited(Order $order, ?int $userId = null, ?string $notes = null): OrderAction
    {
        return $this->log($order, 'edited', null, null, $userId, $notes);
    }

    public function noteAdded(Order $order, string $note, ?int $userId = null): OrderAction
    {
        return $this->log($order, 'note_added', null, null, $userId, $note);
    }
}
