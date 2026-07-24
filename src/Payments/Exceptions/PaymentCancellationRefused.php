<?php

namespace Rahat1994\SparkCommerce\Payments\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The payment processor refused to cancel an outstanding payment — e.g.
 * Stripe's `payment_intent_unexpected_state` when the intent is mid-flight
 * or already succeeded. Callers must keep the order alive: a charge that
 * cannot be cancelled must never be orphaned by expiring or superseding
 * its order.
 */
class PaymentCancellationRefused extends RuntimeException
{
    public static function forOrder(int | string $orderId, ?Throwable $previous = null): self
    {
        return new self(
            "The payment gateway refused to cancel the payment for order [{$orderId}].",
            previous: $previous,
        );
    }
}
