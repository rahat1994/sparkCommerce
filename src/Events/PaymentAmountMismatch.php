<?php

namespace Rahat1994\SparkCommerce\Events;

use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * A verified gateway notification reported an amount or currency that does
 * NOT match the order (KTD17). The order was flagged
 * (`meta.payment_flag = 'amount_mismatch'`) but deliberately NOT
 * transitioned — a human must look at it. The admin mail listener arrives
 * in U14; this event is the seam.
 */
class PaymentAmountMismatch
{
    public function __construct(
        public SCOrder $order,
        public int $amountReceivedCents,
        public string $currency,
    ) {}
}
