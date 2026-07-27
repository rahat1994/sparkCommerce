<?php

namespace Rahat1994\SparkCommerce\Listeners;

use Illuminate\Support\Facades\Notification;
use Rahat1994\SparkCommerce\Concerns\NotifiesAdmin;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Events\OrderTransitioned;
use Rahat1994\SparkCommerce\Notifications\NewOrderReceived;
use Rahat1994\SparkCommerce\Notifications\OrderConfirmation;

/**
 * Order mails on the paid transition (R15/U14): the customer's order
 * confirmation and the admin's new-order mail hang on
 * `OrderTransitioned` with `to === Paid` — the single seam every paid
 * order passes through, webhook-paid and zero-total free orders alike
 * (FreeOrderPlaced deliberately feeds ONLY the free-order admin alert,
 * so a free order is never double-mailed).
 *
 * Unpaid orders never mail by construction: creation is not a transition,
 * and expiry/cancellation transitions have `to !== Paid`.
 *
 * Legacy orders can have a null user — the customer mail skips
 * gracefully; the admin mail skips when no admin email is configured
 * (see {@see NotifiesAdmin}).
 */
class SendOrderPaidNotifications
{
    use NotifiesAdmin;

    public function __invoke(OrderTransitioned $event): void
    {
        if ($event->to !== OrderStatus::Paid) {
            return;
        }

        $customer = $event->order->user;

        if ($customer !== null) {
            Notification::send($customer, new OrderConfirmation($event->order));
        }

        $this->notifyAdmin(new NewOrderReceived($event->order));
    }
}
