<?php

namespace Rahat1994\SparkCommerce\Events;

use Rahat1994\SparkCommerce\Models\SCRefund;

/**
 * A refund attempt failed — either the synchronous gateway call threw, or
 * the gateway later reported `refund.failed` for a refund it had accepted.
 * The refund row already carries the failure reason. U14 mail seam.
 */
class RefundFailed
{
    public function __construct(
        public SCRefund $refund,
    ) {}
}
