<?php

namespace Rahat1994\SparkCommerce\Events;

use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * A checkout completed with a zero grand total (after discounts and
 * shipping) and the order was transitioned straight to Paid without a
 * payment.
 *
 * Dispatched after the paid transition has committed; the admin
 * notification listener arrives in a later unit (U14) — this event is only
 * the seam.
 */
class FreeOrderPlaced
{
    public function __construct(
        public SCOrder $order,
    ) {}
}
