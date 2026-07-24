<?php

namespace Rahat1994\SparkCommerce\Listeners;

use Illuminate\Support\Facades\Notification;
use Rahat1994\SparkCommerce\Events\OrderRefunded;
use Rahat1994\SparkCommerce\Notifications\OrderRefunded as OrderRefundedNotification;

/**
 * Customer refund mail (R15/U14): fires on the refund-succeeded seam
 * ({@see OrderRefunded}, dispatched after commit by RefundService for
 * both the synchronous and the webhook-confirmed success), so full,
 * partial and automatic late-payment refunds all mail the exact amount.
 * A legacy order without a user skips gracefully.
 */
class SendOrderRefundedNotification
{
    public function __invoke(OrderRefunded $event): void
    {
        $customer = $event->order->user;

        if ($customer === null) {
            return;
        }

        Notification::send($customer, new OrderRefundedNotification($event->order, $event->refund));
    }
}
