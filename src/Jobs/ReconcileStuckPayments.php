<?php

namespace Rahat1994\SparkCommerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Events\PaymentNeedsReconciliation;
use Rahat1994\SparkCommerce\Jobs\Concerns\AlertsAdminOnFailure;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Payments\PaymentGatewayManager;
use Throwable;

/**
 * Safety net for dropped webhooks: every awaiting_payment order past its
 * TTL that HAS a transaction_id is asked back from its gateway. A payment
 * that actually SUCCEEDED is flagged (`meta.payment_flag =
 * 'needs_reconciliation'`) and announced through
 * {@see PaymentNeedsReconciliation} — never transitioned here; the webhook
 * pipeline stays the sole paid authority. Everything else is left for the
 * expiry sweep.
 *
 * Scheduled every thirty minutes by SparkCommerceServiceProvider.
 */
class ReconcileStuckPayments implements ShouldQueue
{
    use AlertsAdminOnFailure;
    use Dispatchable;
    use Queueable;

    public function handle(PaymentGatewayManager $paymentGatewayManager): void
    {
        SCOrder::query()
            ->where('status', OrderStatus::AwaitingPayment)
            ->whereNotNull('transaction_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($paymentGatewayManager): void {
                foreach ($orders as $order) {
                    $this->reconcileOrder($order, $paymentGatewayManager);
                }
            });
    }

    /**
     * Per-order isolation: one unreachable gateway must never halt the run.
     */
    protected function reconcileOrder(SCOrder $order, PaymentGatewayManager $paymentGatewayManager): void
    {
        if (Arr::get((array) $order->meta, 'payment_flag') === 'needs_reconciliation') {
            // Already flagged on an earlier run; do not re-alert every half
            // hour while the admin works through it.
            return;
        }

        try {
            $status = $paymentGatewayManager
                ->driver((string) $order->payment_gateway)
                ->retrievePaymentStatus($order);
        } catch (Throwable $exception) {
            Log::error('Payment reconciliation could not retrieve a payment status.', [
                'order_id' => $order->getKey(),
                'gateway' => $order->payment_gateway,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        if ($status !== 'succeeded') {
            return;
        }

        $order->meta = array_merge((array) $order->meta, ['payment_flag' => 'needs_reconciliation']);
        $order->save();

        event(new PaymentNeedsReconciliation($order));
    }
}
