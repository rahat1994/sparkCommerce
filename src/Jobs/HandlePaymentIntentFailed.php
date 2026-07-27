<?php

namespace Rahat1994\SparkCommerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Enums\PaymentStatus;
use Rahat1994\SparkCommerce\Jobs\Concerns\AlertsAdminOnFailure;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Payments\Concerns\InteractsWithPaymentIntentEvents;
use Rahat1994\SparkCommerce\Payments\PaymentEventClaims;

/**
 * A verified payment_intent.payment_failed: record the failure on
 * `payment_status` ONLY. The order's `status` does not move — it stays
 * awaiting_payment and fully retryable until it is paid or the expiry
 * sweep collects it. (payment_status-only writes happen directly on the
 * model, matching how the transition service writes the column; the state
 * machine governs `status`, not `payment_status`.)
 */
class HandlePaymentIntentFailed implements ShouldQueue
{
    use AlertsAdminOnFailure;
    use Dispatchable;
    use InteractsWithPaymentIntentEvents;
    use Queueable;

    /**
     * @param  array<string, mixed>  $event  the verified gateway event
     */
    public function __construct(
        public string $gateway,
        public array $event,
    ) {}

    public function handle(): void
    {
        $eventId = (string) Arr::get($this->event, 'id', '');

        if ($eventId === '') {
            Log::warning('Payment failed event without an id was ignored.', [
                'gateway' => $this->gateway,
            ]);

            return;
        }

        DB::transaction(function () use ($eventId): void {
            if (! PaymentEventClaims::claim($this->gateway, $eventId)) {
                return;
            }

            $order = $this->locateOrder($this->paymentIntent());

            if ($order === null) {
                return;
            }

            /** @var SCOrder $locked */
            $locked = SCOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->status !== OrderStatus::AwaitingPayment) {
                return;
            }

            $locked->payment_status = PaymentStatus::Failed;
            $locked->save();

            Log::info('Payment attempt failed; the order stays awaiting payment.', [
                'gateway' => $this->gateway,
                'order_id' => $locked->getKey(),
                'event_id' => $eventId,
            ]);
        });
    }
}
