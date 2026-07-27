<?php

namespace Rahat1994\SparkCommerce\Listeners;

use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Events\OrderTransitioned;
use Rahat1994\SparkCommerce\Models\SCProduct;

/**
 * Give reserved stock back when an unpaid order leaves the system:
 * AwaitingPayment -> Cancelled and AwaitingPayment -> Expired restore each
 * snapshot item's quantity via {@see SCProduct::releaseStock()} (which is a
 * no-op for products whose stock is not managed).
 *
 * Release-once-only: {@see OrderTransitioned} fires only after a committed
 * status write, and Cancelled/Expired are terminal states of the one-time
 * state machine — an already-cancelled order cannot transition again
 * (OrderTransitionService throws IllegalOrderTransition under a row lock),
 * so a double release is impossible by construction.
 *
 * Paid-order refund restock is deliberately NOT handled here (U13).
 */
class ReleaseReservedStock
{
    public function __invoke(OrderTransitioned $event): void
    {
        if ($event->from !== OrderStatus::AwaitingPayment) {
            return;
        }

        if (! in_array($event->to, [OrderStatus::Cancelled, OrderStatus::Expired], true)) {
            return;
        }

        foreach ((array) $event->order->items as $snapshot) {
            $itemableType = $snapshot['itemable_type'] ?? null;
            $quantity = (int) ($snapshot['quantity'] ?? 0);

            if ($quantity < 1 || ! is_string($itemableType) || ! is_a($itemableType, SCProduct::class, true)) {
                continue;
            }

            SCProduct::query()->find($snapshot['itemable_id'] ?? null)?->releaseStock($quantity);
        }
    }
}
