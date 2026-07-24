<?php

namespace Rahat1994\SparkCommerce\Jobs;

use Binafy\LaravelCart\Models\Cart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Enums\PaymentStatus;
use Rahat1994\SparkCommerce\Events\LatePaymentReceived;
use Rahat1994\SparkCommerce\Events\PaymentAmountMismatch;
use Rahat1994\SparkCommerce\Models\SCCoupon;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Payments\Concerns\InteractsWithPaymentIntentEvents;
use Rahat1994\SparkCommerce\Payments\PaymentEventClaims;
use Rahat1994\SparkCommerce\Services\OrderTransitionService;

/**
 * THE paid authority (R2): a VERIFIED gateway succeeded-notification is the
 * only thing that ever marks an order Paid. Dispatched by a driver's
 * handleWebhook AFTER signature verification.
 *
 * Atomicity: one outer DB::transaction wraps the event claim and every
 * side effect. The OrderTransitionService opens its own transaction +
 * lockForUpdate inside — under Laravel that inner DB::transaction becomes
 * a SAVEPOINT of the outer one, so the claim row, the status write, the
 * coupon usage and the cart deletion all commit (or roll back) together,
 * while the service's row lock still serializes against the expiry sweep.
 */
class HandlePaymentIntentSucceeded implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithPaymentIntentEvents;
    use Queueable;

    /**
     * @param  array<string, mixed>  $event  the verified gateway event
     *                                       (`{id, type, data: {object: payment_intent}}`)
     */
    public function __construct(
        public string $gateway,
        public array $event,
    ) {}

    public function handle(OrderTransitionService $orderTransitionService): void
    {
        $eventId = (string) Arr::get($this->event, 'id', '');

        if ($eventId === '') {
            Log::warning('Payment succeeded event without an id was ignored.', [
                'gateway' => $this->gateway,
            ]);

            return;
        }

        DB::transaction(function () use ($orderTransitionService, $eventId): void {
            // Exactly-once: the claim shares the transaction with every
            // side effect below. A redelivery loses the unique-index race
            // and no-ops here.
            if (! PaymentEventClaims::claim($this->gateway, $eventId)) {
                return;
            }

            $intent = $this->paymentIntent();

            $order = $this->locateOrder($intent);

            if ($order === null) {
                // Unknown intent: ack (the claim stands so redeliveries stay
                // quiet) and log — never guess at an order.
                Log::warning('Payment succeeded for an unknown payment intent.', [
                    'gateway' => $this->gateway,
                    'event_id' => $eventId,
                    'intent_id' => Arr::get($intent, 'id'),
                ]);

                return;
            }

            /** @var SCOrder $locked */
            $locked = SCOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            // KTD17: the money must match EXACTLY — amount received and
            // currency — before anything is marked paid.
            $amountReceived = (int) Arr::get($intent, 'amount_received', -1);
            $currency = strtoupper((string) Arr::get($intent, 'currency', ''));

            if ($amountReceived !== (int) $locked->getRawOriginal('total_amount_cents')
                || $currency !== strtoupper((string) $locked->currency)) {
                $this->flagOrder($locked, 'amount_mismatch');

                event(new PaymentAmountMismatch($locked, $amountReceived, $currency));

                return;
            }

            if ($locked->status === OrderStatus::Expired) {
                // Reservations are long released; never resurrect. U13's
                // auto-refund listens on this event.
                $this->flagOrder($locked, 'paid_after_expiry');

                event(new LatePaymentReceived($locked, $amountReceived));

                return;
            }

            if ($locked->status !== OrderStatus::AwaitingPayment) {
                // Already Paid (a redelivery under a new event id) or
                // otherwise progressed: nothing to do.
                return;
            }

            $paid = $orderTransitionService->transition(
                $locked,
                OrderStatus::Paid,
                ['payment_status' => PaymentStatus::Paid],
            );

            $this->recordCouponUsage($paid);
            $this->deleteCustomerCart($paid);
        });
    }

    /**
     * Multi-use coupons record their redemption at PAID, here.
     * recordUsageFor itself skips the global counter when the order's
     * discount json says the checkout already `reserved` a single-use slot.
     */
    protected function recordCouponUsage(SCOrder $order): void
    {
        $couponId = data_get($order->discount, 'coupon_id');

        if ($couponId === null) {
            return;
        }

        $coupon = SCCoupon::query()->find($couponId);

        if ($coupon === null) {
            Log::warning('Paid order references a coupon that no longer exists.', [
                'order_id' => $order->getKey(),
                'coupon_id' => $couponId,
            ]);

            return;
        }

        $coupon->recordUsageFor($order);
    }

    /**
     * The U11 boundary: the cart SURVIVES checkout and is deleted only
     * here, when the order is actually paid. Checkout reads the cart as
     * `Cart::query()->firstOrCreate(['user_id' => $user->id])`, so the
     * user's Cart row (with its items) is exactly what gets deleted.
     */
    protected function deleteCustomerCart(SCOrder $order): void
    {
        if ($order->user_id === null) {
            return;
        }

        $cart = Cart::query()->where('user_id', $order->user_id)->first();

        if ($cart === null) {
            return;
        }

        $cart->items()->delete();
        $cart->delete();
    }
}
