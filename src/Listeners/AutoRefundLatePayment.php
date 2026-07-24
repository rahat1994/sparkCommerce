<?php

namespace Rahat1994\SparkCommerce\Listeners;

use Illuminate\Support\Facades\Log;
use Rahat1994\SparkCommerce\Events\LatePaymentReceived;
use Rahat1994\SparkCommerce\Events\OrderAutoRefunded;
use Rahat1994\SparkCommerce\Services\RefundService;
use Throwable;

/**
 * KTD13: a payment that succeeds AFTER its order expired is refunded
 * automatically, for exactly the cents the gateway reported received.
 *
 * `restock: false` is deliberate and mandatory: the AwaitingPayment ->
 * Expired transition already released the stock reservations
 * (ReleaseReservedStock), so restocking here would double the stock.
 * `allowExpired: true` opens the otherwise-forbidden Expired state for
 * this one system path; the order STAYS Expired — only its payment_status
 * ends at Refunded.
 *
 * A gateway failure is logged and swallowed: the
 * `meta.payment_flag = 'paid_after_expiry'` flag is already on the order,
 * so the admin sees the stuck money — throwing out of this listener would
 * abort the webhook job's claim transaction and unmark the handled event.
 */
class AutoRefundLatePayment
{
    public function __construct(
        protected RefundService $refundService,
    ) {}

    public function __invoke(LatePaymentReceived $event): void
    {
        try {
            $refund = $this->refundService->refund(
                $event->order,
                $event->amountReceivedCents,
                initiatedBy: null,
                restock: false,
                allowExpired: true,
            );
        } catch (Throwable $exception) {
            Log::error('Automatic late-payment refund failed; the order keeps its paid_after_expiry flag.', [
                'order_id' => $event->order->getKey(),
                'amount_cents' => $event->amountReceivedCents,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        event(new OrderAutoRefunded($event->order, $refund));
    }
}
