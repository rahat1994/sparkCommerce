<?php

namespace Rahat1994\SparkCommerce\Enums;

use Rahat1994\SparkCommerce\Models\SCProduct;

/**
 * The three legacy values stored in `sc_products.should_allow_backorders`
 * (see the ProductResource inventory form). A NULL column value is treated
 * as DoNotAllow by {@see SCProduct::backorderPolicy()}.
 */
enum BackorderPolicy: string
{
    case DoNotAllow = 'do_not_allow';
    case Allow = 'allow';
    case AllowNotifyCustomer = 'allow_notify_customer';

    public function allowsBackorder(): bool
    {
        return $this !== self::DoNotAllow;
    }
}
