<?php

namespace Rahat1994\SparkCommerce\Services;

use Illuminate\Support\Facades\DB;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Enums\PaymentStatus;
use Rahat1994\SparkCommerce\Events\OrderTransitioned;
use Rahat1994\SparkCommerce\Events\OrderTransitioning;
use Rahat1994\SparkCommerce\Exceptions\IllegalOrderTransition;
use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * The ONLY writer of an order's `status` after the order exists.
 *
 * Boundary: order CREATION (checkout) is not a transition — creating an
 * order with its initial `awaiting_payment` status (and `pending` payment
 * status) directly is allowed. Every status change AFTER creation must go
 * through {@see transition()}.
 */
class OrderTransitionService
{
    /**
     * Move an order to a new status through the state machine.
     *
     * The order row is re-fetched with a `lockForUpdate()` inside the
     * transaction: that row lock is the serialization point concurrent
     * writers (payment webhook vs. expiry sweep) rely on — do not remove it.
     *
     * A listener of {@see OrderTransitioning} that throws aborts the
     * transition; {@see OrderTransitioned} fires only after the status write
     * has been committed.
     *
     * @param  array{payment_status?: PaymentStatus|string|null}  $context
     *
     * @throws IllegalOrderTransition when the state machine forbids the move
     */
    public function transition(SCOrder $order, OrderStatus $to, array $context = []): SCOrder
    {
        return DB::transaction(function () use ($order, $to, $context): SCOrder {
            /** @var SCOrder $locked */
            $locked = SCOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            $from = $locked->status;

            if ($from === null || ! $from->canTransitionTo($to)) {
                throw IllegalOrderTransition::make($from, $to);
            }

            event(new OrderTransitioning($locked, $from, $to));

            $locked->status = $to;

            $paymentStatus = $context['payment_status'] ?? null;

            if ($paymentStatus !== null) {
                $locked->payment_status = $paymentStatus instanceof PaymentStatus
                    ? $paymentStatus
                    : PaymentStatus::from($paymentStatus);
            }

            $locked->save();

            DB::afterCommit(function () use ($locked, $from, $to): void {
                event(new OrderTransitioned($locked, $from, $to));
            });

            return $locked;
        });
    }
}
