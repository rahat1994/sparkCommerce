<?php

namespace Rahat1994\SparkCommerce\Jobs\Concerns;

use Rahat1994\SparkCommerce\Concerns\NotifiesAdmin;
use Rahat1994\SparkCommerce\Enums\AdminAlertReason;
use Rahat1994\SparkCommerce\Notifications\AdminPaymentAlert;
use Throwable;

/**
 * The queue `failed()` hook for the payment webhook/maintenance jobs
 * (R15/U14): when a job exhausts its retries the store operator is alerted,
 * so a dropped payment notification never fails silently on the queue.
 *
 * The one {@see AdminPaymentAlert} carries the {@see AdminAlertReason::JobFailed}
 * reason; delivery goes to the configured `sparkcommerce.admin_email` via
 * {@see NotifiesAdmin} (and is silently skipped when no admin email is set,
 * exactly like every other admin alert).
 */
trait AlertsAdminOnFailure
{
    use NotifiesAdmin;

    public function failed(?Throwable $exception): void
    {
        $this->notifyAdmin(new AdminPaymentAlert(AdminAlertReason::JobFailed, [
            'job' => class_basename(static::class),
            'gateway' => property_exists($this, 'gateway') ? (string) $this->gateway : 'n/a',
            'exception' => $exception?->getMessage() ?? 'unknown',
        ]));
    }
}
