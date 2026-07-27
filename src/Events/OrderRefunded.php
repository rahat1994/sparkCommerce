<?php

namespace Rahat1994\SparkCommerce\Events;

use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCRefund;
use Rahat1994\SparkCommerce\Services\RefundService;

/**
 * A refund row reached Succeeded and the write is committed (U14).
 *
 * Dispatched by {@see RefundService}
 * after commit, from BOTH success seams: the synchronous gateway answer
 * and the `charge.refunded` webhook confirmation of a still-pending row.
 * Exactly once per refund row by construction — the webhook confirmation
 * no-ops on a row the synchronous path already marked succeeded.
 */
class OrderRefunded
{
    public function __construct(
        public SCOrder $order,
        public SCRefund $refund,
    ) {}
}
