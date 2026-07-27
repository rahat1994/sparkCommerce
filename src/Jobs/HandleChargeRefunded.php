<?php

namespace Rahat1994\SparkCommerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rahat1994\SparkCommerce\Enums\RefundStatus;
use Rahat1994\SparkCommerce\Jobs\Concerns\AlertsAdminOnFailure;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCRefund;
use Rahat1994\SparkCommerce\Payments\PaymentEventClaims;
use Rahat1994\SparkCommerce\Services\RefundService;

/**
 * A verified `charge.refunded`: idempotent CONFIRMATION of refunds the
 * synchronous path already recorded (sync is authoritative — see
 * {@see RefundService}). Each gateway refund id on the charge is matched
 * to its local row; a still-pending row is flipped to succeeded and the
 * order's payment state re-derived. Restocking NEVER happens here — the
 * `restocked` flag belongs to the synchronous path alone, so redelivery
 * (blocked by the event claim anyway) can never double stock.
 *
 * Same savepoint atomicity as HandlePaymentIntentSucceeded: the claim and
 * every side effect share one outer transaction.
 */
class HandleChargeRefunded implements ShouldQueue
{
    use AlertsAdminOnFailure;
    use Dispatchable;
    use Queueable;

    /**
     * @param  array<string, mixed>  $event  the verified gateway event
     *                                       (`{id, type, data: {object: charge}}`)
     */
    public function __construct(
        public string $gateway,
        public array $event,
    ) {}

    public function handle(RefundService $refundService): void
    {
        $eventId = (string) Arr::get($this->event, 'id', '');

        if ($eventId === '') {
            Log::warning('Charge refunded event without an id was ignored.', [
                'gateway' => $this->gateway,
            ]);

            return;
        }

        DB::transaction(function () use ($refundService, $eventId): void {
            if (! PaymentEventClaims::claim($this->gateway, $eventId)) {
                return;
            }

            /** @var array<string, mixed> $charge */
            $charge = (array) Arr::get($this->event, 'data.object', []);

            $confirmed = false;

            foreach ((array) Arr::get($charge, 'refunds.data', []) as $gatewayRefund) {
                $gatewayRefundId = (string) Arr::get((array) $gatewayRefund, 'id', '');

                if ($gatewayRefundId === '') {
                    continue;
                }

                $refund = SCRefund::query()->where('gateway_refund_id', $gatewayRefundId)->first();

                if ($refund === null) {
                    continue;
                }

                $refundService->confirmFromWebhook($refund, $gatewayRefundId);
                $confirmed = true;
            }

            if ($confirmed) {
                return;
            }

            // Fallback: no refund id matched a local row (e.g. the process
            // died before the gateway answer was stored). Locate the order
            // via the payment intent and match a pending row by amount.
            $fallback = $this->pendingRefundByIntentAndAmount($charge);

            if ($fallback === null) {
                // Ack and log: the claim stands so redeliveries stay quiet.
                Log::warning('Charge refunded event matched no local refund.', [
                    'gateway' => $this->gateway,
                    'event_id' => $eventId,
                    'payment_intent' => Arr::get($charge, 'payment_intent'),
                ]);

                return;
            }

            $refundService->confirmFromWebhook($fallback);
        });
    }

    /**
     * The charge's `amount_refunded` is CUMULATIVE; the still-unconfirmed
     * slice is that total minus everything already succeeded locally. A
     * single pending refund of exactly that amount is an unambiguous match.
     *
     * @param  array<string, mixed>  $charge
     */
    protected function pendingRefundByIntentAndAmount(array $charge): ?SCRefund
    {
        $intentId = (string) Arr::get($charge, 'payment_intent', '');

        if ($intentId === '') {
            return null;
        }

        $order = SCOrder::query()->where('transaction_id', $intentId)->first();

        if ($order === null) {
            return null;
        }

        $succeededCents = (int) SCRefund::query()
            ->where('order_id', $order->getKey())
            ->where('status', RefundStatus::Succeeded)
            ->sum('amount_cents');

        $unconfirmedCents = (int) Arr::get($charge, 'amount_refunded', 0) - $succeededCents;

        if ($unconfirmedCents <= 0) {
            return null;
        }

        return SCRefund::query()
            ->where('order_id', $order->getKey())
            ->where('status', RefundStatus::Pending)
            ->where('amount_cents', $unconfirmedCents)
            ->first();
    }
}
