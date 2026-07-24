<?php

namespace Rahat1994\SparkCommerce\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Rahat1994\SparkCommerce\Events\LatePaymentReceived;
use Rahat1994\SparkCommerce\Events\OrderAutoRefunded;
use Rahat1994\SparkCommerce\Services\RefundService;
use Throwable;

/**
 * KTD13: a payment that succeeds AFTER its order was already closed
 * (Expired or Cancelled) is refunded automatically, for exactly the cents
 * the gateway reported received.
 *
 * Queued (ShouldQueue) on purpose: {@see LatePaymentReceived} is dispatched
 * from INSIDE the payment webhook's row-locked DB::transaction. Running the
 * live gateway refund here synchronously would make that network call a mere
 * savepoint of the webhook transaction — holding the order row lock across a
 * Stripe round-trip. Queuing defers the refund until AFTER the webhook
 * transaction has committed (the job row is only visible to a worker once the
 * transaction commits), so the RefundService call is a top-level transaction
 * of its own.
 *
 * `restock: false` is deliberate and mandatory: the AwaitingPayment ->
 * Expired/Cancelled transition already released the stock reservations
 * (ReleaseReservedStock), so restocking here would double the stock.
 * `allowExpired: true` opens the otherwise-forbidden terminal states
 * (Expired / Cancelled) for this one system path; the order STAYS closed —
 * only its payment_status ends at Refunded.
 *
 * A gateway failure is logged and swallowed: the
 * `meta.payment_flag` (`paid_after_expiry` / `paid_after_cancel`) is already
 * on the order, so the admin sees the stuck money.
 */
class AutoRefundLatePayment implements ShouldQueue
{
    use InteractsWithQueue;

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
            Log::error('Automatic late-payment refund failed; the order keeps its payment_flag.', [
                'order_id' => $event->order->getKey(),
                'amount_cents' => $event->amountReceivedCents,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        event(new OrderAutoRefunded($event->order, $refund));
    }
}
