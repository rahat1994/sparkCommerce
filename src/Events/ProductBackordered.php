<?php

namespace Rahat1994\SparkCommerce\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Rahat1994\SparkCommerce\Models\SCProduct;

/**
 * A customer put a quantity into their cart that exceeds the managed stock
 * of a product whose backorder policy is AllowNotifyCustomer.
 *
 * Dispatched at add-to-cart time; the queued notification listener arrives
 * in a later unit (U14) — this event is only the seam.
 */
class ProductBackordered
{
    public function __construct(
        public SCProduct $product,
        public ?Authenticatable $user,
        public int $quantity,
    ) {}
}
