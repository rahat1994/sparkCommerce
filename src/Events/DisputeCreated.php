<?php

namespace Rahat1994\SparkCommerce\Events;

use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * The processor opened a dispute (chargeback) against the order's charge
 * (`charge.dispute.created`). The order was flagged
 * (`meta.payment_flag = 'disputed'`) so the admin sees it. U14 mail seam.
 */
class DisputeCreated
{
    /**
     * @param  array<string, mixed>  $dispute  the gateway's dispute object
     */
    public function __construct(
        public SCOrder $order,
        public array $dispute = [],
    ) {}
}
