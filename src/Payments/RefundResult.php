<?php

namespace Rahat1994\SparkCommerce\Payments;

/**
 * The gateway-neutral result of {@see Contracts\PaymentGateway::refund()}.
 */
final readonly class RefundResult
{
    /**
     * @param  string  $reference  the processor's refund id
     * @param  string  $status  the processor-reported refund status
     */
    public function __construct(
        public string $reference,
        public string $status,
    ) {}
}
