<?php

namespace Rahat1994\SparkCommerce\Listeners;

use Illuminate\Support\Facades\Log;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Events\OrderTransitioned;
use Rahat1994\SparkCommerce\Models\SCCoupon;

/**
 * Give a reserved single-use coupon back when an unpaid order leaves the
 * system: AwaitingPayment -> Cancelled and AwaitingPayment -> Expired
 * release the claim via {@see SCCoupon::releaseSingleUse()} when the
 * order's `discount` json says the checkout reserved one
 * (`reserved: true`, written next to `coupon_id`).
 *
 * Release-once-only holds for the same reason as ReleaseReservedStock:
 * Cancelled/Expired are terminal states of the one-time state machine, so
 * the transition that triggers the release can only happen once per order.
 *
 * A coupon that has been deleted since checkout is logged and skipped —
 * this listener never throws.
 */
class ReleaseCouponReservation
{
    public function __invoke(OrderTransitioned $event): void
    {
        if ($event->from !== OrderStatus::AwaitingPayment) {
            return;
        }

        if (! in_array($event->to, [OrderStatus::Cancelled, OrderStatus::Expired], true)) {
            return;
        }

        $discount = (array) $event->order->discount;

        if (($discount['reserved'] ?? false) !== true) {
            return;
        }

        $couponId = $discount['coupon_id'] ?? null;
        $coupon = $couponId !== null ? SCCoupon::query()->find($couponId) : null;

        if ($coupon === null) {
            Log::warning('ReleaseCouponReservation skipped: reserved coupon no longer exists.', [
                'order_id' => $event->order->getKey(),
                'coupon_id' => $couponId,
            ]);

            return;
        }

        $coupon->releaseSingleUse();
    }
}
