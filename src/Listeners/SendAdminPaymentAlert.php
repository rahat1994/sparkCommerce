<?php

namespace Rahat1994\SparkCommerce\Listeners;

use Rahat1994\SparkCommerce\Concerns\NotifiesAdmin;
use Rahat1994\SparkCommerce\Enums\AdminAlertReason;
use Rahat1994\SparkCommerce\Events\DisputeCreated;
use Rahat1994\SparkCommerce\Events\FreeOrderPlaced;
use Rahat1994\SparkCommerce\Events\OrderAutoRefunded;
use Rahat1994\SparkCommerce\Events\PaymentAmountMismatch;
use Rahat1994\SparkCommerce\Events\PaymentNeedsReconciliation;
use Rahat1994\SparkCommerce\Events\RefundFailed;
use Rahat1994\SparkCommerce\Events\WebhookSignatureFailing;
use Rahat1994\SparkCommerce\Notifications\AdminPaymentAlert;

/**
 * The one thin mapper from every operational payment event to its
 * {@see AdminPaymentAlert} reason (R15/U14). Registered once per event
 * class in the provider; the match below turns the event's payload into
 * the scalar lang-replacement context the alert renders from.
 *
 * LatePaymentReceived is deliberately absent: the auto-refund listener
 * consumes it and reports the OUTCOME — OrderAutoRefunded on success
 * (late_payment_refunded here) or RefundFailed when the gateway refused
 * (refund_failed here).
 */
class SendAdminPaymentAlert
{
    use NotifiesAdmin;

    public function __invoke(object $event): void
    {
        $alert = match ($event::class) {
            PaymentAmountMismatch::class => new AdminPaymentAlert(AdminAlertReason::AmountMismatch, [
                'order_number' => (string) $event->order->order_number,
                'amount' => $this->formatCents($event->amountReceivedCents),
                'currency' => $event->currency,
                'expected' => $this->formatCents((int) $event->order->getRawOriginal('total_amount_cents')),
            ]),
            OrderAutoRefunded::class => new AdminPaymentAlert(AdminAlertReason::LatePaymentRefunded, [
                'order_number' => (string) $event->order->order_number,
                'amount' => $this->formatCents((int) $event->refund->getRawOriginal('amount_cents')),
                'currency' => (string) $event->refund->currency,
            ]),
            DisputeCreated::class => new AdminPaymentAlert(AdminAlertReason::DisputeCreated, [
                'order_number' => (string) $event->order->order_number,
            ]),
            RefundFailed::class => new AdminPaymentAlert(AdminAlertReason::RefundFailed, [
                'order_number' => (string) ($event->refund->order?->order_number ?? "#{$event->refund->order_id}"),
                'amount' => $this->formatCents((int) $event->refund->getRawOriginal('amount_cents')),
                'currency' => (string) $event->refund->currency,
                'failure_reason' => (string) ($event->refund->failure_reason ?? 'unknown'),
            ]),
            WebhookSignatureFailing::class => new AdminPaymentAlert(AdminAlertReason::WebhookSignatureFailing, [
                'gateway' => $event->gateway,
                'failures' => $event->failuresThisHour,
            ]),
            PaymentNeedsReconciliation::class => new AdminPaymentAlert(AdminAlertReason::NeedsReconciliation, [
                'order_number' => (string) $event->order->order_number,
            ]),
            FreeOrderPlaced::class => new AdminPaymentAlert(AdminAlertReason::FreeOrderPlaced, [
                'order_number' => (string) $event->order->order_number,
            ]),
            default => null,
        };

        if ($alert !== null) {
            $this->notifyAdmin($alert);
        }
    }

    protected function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
