<?php

namespace Rahat1994\SparkCommerce\Events;

use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * Reconciliation found an awaiting_payment order past its TTL whose
 * gateway payment has actually SUCCEEDED — the paid webhook was dropped or
 * never processed. The order was flagged
 * (`meta.payment_flag = 'needs_reconciliation'`); the admin alert listener
 * arrives in U14. This event is the seam.
 */
class PaymentNeedsReconciliation
{
    public function __construct(
        public SCOrder $order,
    ) {}
}
