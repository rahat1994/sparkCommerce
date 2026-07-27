<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Delivery to the store operator (U14): admin mails go to the configured
 * `sparkcommerce.admin_email` through an on-demand mail route — the admin
 * is an address, never a user row.
 *
 * Documented choice: when no admin email is configured every admin mail
 * is skipped SILENTLY. The key is optional by design (a dev install has
 * no operator mailbox) and logging on every skipped event would flood the
 * log of a store that deliberately runs without admin mail.
 */
trait NotifiesAdmin
{
    protected function notifyAdmin(BaseNotification $notification): void
    {
        $adminEmail = config('sparkcommerce.admin_email');

        if (! is_string($adminEmail) || trim($adminEmail) === '') {
            return;
        }

        Notification::route('mail', $adminEmail)->notify($notification);
    }
}
