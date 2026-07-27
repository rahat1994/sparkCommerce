<?php

namespace Rahat1994\SparkCommerce\Events;

use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCRefund;

/**
 * A payment that succeeded AFTER its order expired was automatically
 * refunded by the system (KTD13). The order stays Expired; only its
 * payment_status reflects the returned money. U14 mail seam.
 */
class OrderAutoRefunded
{
    public function __construct(
        public SCOrder $order,
        public SCRefund $refund,
    ) {}
}
