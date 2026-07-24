<?php

namespace Rahat1994\SparkCommerce\Events;

use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * Fired inside the transition transaction, BEFORE the status is written.
 *
 * This is the extension seam for add-on packages (e.g. multivendor): a
 * listener that throws aborts the transition and rolls the write back.
 */
class OrderTransitioning
{
    public function __construct(
        public SCOrder $order,
        public OrderStatus $from,
        public OrderStatus $to,
    ) {}
}
