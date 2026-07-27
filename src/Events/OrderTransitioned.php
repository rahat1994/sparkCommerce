<?php

namespace Rahat1994\SparkCommerce\Events;

use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * Fired AFTER the status write has been committed to the database.
 *
 * Listeners can rely on the new status being durable; throwing here can no
 * longer abort the transition.
 */
class OrderTransitioned
{
    public function __construct(
        public SCOrder $order,
        public OrderStatus $from,
        public OrderStatus $to,
    ) {}
}
