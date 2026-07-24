<?php

namespace Rahat1994\SparkCommerce\Payments\Concerns;

use Illuminate\Support\Arr;
use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * Shared helpers for the queued webhook jobs working on a gateway event
 * array shaped like Stripe's (`{id, type, data: {object: {...}}}` — the
 * FakeGateway and adopter drivers mirror this shape).
 */
trait InteractsWithPaymentIntentEvents
{
    /**
     * The payment intent object carried by the event.
     *
     * @return array<string, mixed>
     */
    protected function paymentIntent(): array
    {
        return (array) Arr::get($this->event, 'data.object', []);
    }

    /**
     * Locate the order the intent pays for: primarily by the stored
     * `transaction_id` (the intent id written at checkout), falling back to
     * the `metadata.order_id` stamped onto the intent at creation.
     *
     * @param  array<string, mixed>  $intent
     */
    protected function locateOrder(array $intent): ?SCOrder
    {
        $intentId = (string) Arr::get($intent, 'id', '');

        $order = $intentId !== ''
            ? SCOrder::query()->where('transaction_id', $intentId)->first()
            : null;

        if ($order !== null) {
            return $order;
        }

        $orderId = Arr::get($intent, 'metadata.order_id');

        return $orderId !== null ? SCOrder::query()->find($orderId) : null;
    }

    /**
     * Merge a payment flag into the order's meta json without touching
     * anything else.
     */
    protected function flagOrder(SCOrder $order, string $flag): void
    {
        $order->meta = array_merge((array) $order->meta, ['payment_flag' => $flag]);
        $order->save();
    }
}
