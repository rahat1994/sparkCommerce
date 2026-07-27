<?php

namespace Rahat1994\SparkCommerce\Listeners;

use Illuminate\Support\Facades\Notification;
use Rahat1994\SparkCommerce\Events\ProductBackordered;
use Rahat1994\SparkCommerce\Notifications\BackorderNotification;

/**
 * Backorder mail (R15/U14): only a signed-in customer can be told their
 * quantity is on backorder — an anonymous cart carries a null user and
 * skips silently.
 */
class SendBackorderNotification
{
    public function __invoke(ProductBackordered $event): void
    {
        if ($event->user === null) {
            return;
        }

        Notification::send($event->user, new BackorderNotification($event->product, $event->quantity));
    }
}
