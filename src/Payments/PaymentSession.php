<?php

namespace Rahat1994\SparkCommerce\Payments;

/**
 * The gateway-neutral result of {@see Contracts\PaymentGateway::createPayment()}.
 *
 * `clientParams` holds whatever the storefront client needs to finish the
 * payment (for Stripe: `['client_secret' => ...]`). It is exposed ONLY in
 * the checkout response — never stored on the order and never served from
 * any order read endpoint.
 */
final readonly class PaymentSession
{
    /**
     * @param  string  $gateway  driver name ('stripe', 'fake', ...)
     * @param  string  $flow  'client_confirm' or 'redirect'
     * @param  string  $reference  the processor's payment id (e.g. PaymentIntent id)
     * @param  array<string, mixed>  $clientParams
     */
    public function __construct(
        public string $gateway,
        public string $flow,
        public string $reference,
        public array $clientParams,
    ) {}
}
