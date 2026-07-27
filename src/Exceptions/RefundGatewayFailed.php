<?php

namespace Rahat1994\SparkCommerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The payment processor declined or errored on a refund call. When this is
 * thrown the local refund row records the gateway's reason; its status
 * depends on whether the failure was definitive — a definitive decline
 * marks the row Failed (the amount is freed), while an ambiguous/transport
 * error LEAVES it Pending for the webhook to reconcile (so a retry can never
 * issue a second real refund). The order's fulfillment status is untouched.
 */
class RefundGatewayFailed extends RuntimeException
{
    public static function forRefund(int | string $refundId, Throwable $previous): self
    {
        return new self(
            sprintf('The payment gateway did not process refund [%s]: %s', $refundId, $previous->getMessage()),
            0,
            $previous,
        );
    }
}
