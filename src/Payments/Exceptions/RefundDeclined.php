<?php

namespace Rahat1994\SparkCommerce\Payments\Exceptions;

use Rahat1994\SparkCommerce\Services\RefundService;
use RuntimeException;
use Throwable;

/**
 * A DEFINITIVE refund decline: the gateway positively confirmed the refund
 * did NOT happen (e.g. the card network rejected it). A driver throws this
 * ONLY when it is certain no money moved.
 *
 * The distinction matters for {@see RefundService}:
 * a definitive decline is safe to mark the local row Failed (the amount is
 * freed for a retry). Any OTHER exception — a network timeout or transport
 * error where the outcome is unknown — is treated as ambiguous and the row
 * is left Pending so a retry can never issue a second real refund.
 */
class RefundDeclined extends RuntimeException
{
    public static function forOrder(int | string $orderId, ?Throwable $previous = null): self
    {
        return new self(
            "The payment gateway declined the refund for order [{$orderId}].",
            previous: $previous,
        );
    }
}
