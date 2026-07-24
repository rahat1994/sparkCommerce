<?php

namespace Rahat1994\SparkCommerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The payment processor refused or errored on a refund call. The local
 * refund row has already been marked failed (with the gateway's reason)
 * when this is thrown; the order itself is untouched.
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
