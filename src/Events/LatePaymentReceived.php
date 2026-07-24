<?php

namespace Rahat1994\SparkCommerce\Events;

use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * A verified payment succeeded for an order that had ALREADY expired. The
 * order is never resurrected (its stock and coupon reservations are long
 * released); it was flagged (`meta.payment_flag = 'paid_after_expiry'`)
 * instead. The auto-refund listener arrives in U13; this event is the seam.
 */
class LatePaymentReceived
{
    public function __construct(
        public SCOrder $order,
    ) {}
}
