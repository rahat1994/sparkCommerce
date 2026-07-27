<?php

namespace Rahat1994\SparkCommerce\Exceptions;

use DomainException;
use Rahat1994\SparkCommerce\Enums\OrderStatus;

/**
 * The refund was rejected BEFORE any gateway call: the order is not in a
 * refundable state, the amount is not positive, or the amount would push
 * the refunded total (pending rows included) past the order total.
 */
class RefundNotAllowed extends DomainException
{
    public static function orderNotRefundable(?OrderStatus $status): self
    {
        return new self(sprintf(
            "Orders in status '%s' cannot be refunded.",
            $status?->value ?? 'null',
        ));
    }

    public static function nonPositiveAmount(int $amountCents): self
    {
        return new self(sprintf('A refund amount must be positive; got %d cents.', $amountCents));
    }

    public static function exceedsRemainingBalance(int $amountCents, int $remainingCents): self
    {
        return new self(sprintf(
            'Refunding %d cents would exceed the remaining refundable balance of %d cents (pending refunds count against it).',
            $amountCents,
            $remainingCents,
        ));
    }
}
