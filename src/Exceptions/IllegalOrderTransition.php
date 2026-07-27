<?php

namespace Rahat1994\SparkCommerce\Exceptions;

use DomainException;
use Rahat1994\SparkCommerce\Enums\OrderStatus;

class IllegalOrderTransition extends DomainException
{
    public static function make(?OrderStatus $from, OrderStatus $to): self
    {
        return new self(sprintf(
            "Illegal order transition from '%s' to '%s'.",
            $from?->value ?? 'null',
            $to->value,
        ));
    }
}
