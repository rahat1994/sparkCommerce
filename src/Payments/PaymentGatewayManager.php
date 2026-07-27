<?php

namespace Rahat1994\SparkCommerce\Payments;

use Illuminate\Support\Manager;
use Rahat1994\SparkCommerce\Payments\Contracts\PaymentGateway;
use Rahat1994\SparkCommerce\Payments\Drivers\FakeGateway;
use Rahat1994\SparkCommerce\Payments\Drivers\StripeGateway;
use RuntimeException;

/**
 * Laravel Manager for payment gateways (R13). Bound as a singleton and
 * aliased to 'sparkcommerce.payments'.
 *
 * Adopters add their own gateways WITHOUT touching this package:
 *
 *     app('sparkcommerce.payments')->extend('mollie', fn () => new MollieGateway(...));
 *
 * The default driver comes from `sparkcommerce.payments.default` — the
 * shipped default is 'stripe'; both package test suites pin it to 'fake'.
 */
class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('sparkcommerce.payments.default', 'stripe');
    }

    protected function createFakeDriver(): PaymentGateway
    {
        // The fake driver is a test double. It must never be resolvable in
        // production: its webhook handler authenticates on a static shared
        // secret (not an HMAC over the body), so a misconfigured secret in
        // production would let a forged event mark orders paid. Restrict it
        // to non-production environments.
        if (! app()->environment(['testing', 'local'])) {
            throw new RuntimeException(
                'The "fake" payment gateway is a test double and cannot be used outside the testing/local environments.'
            );
        }

        return new FakeGateway;
    }

    protected function createStripeDriver(): PaymentGateway
    {
        return new StripeGateway(
            (array) $this->config->get('sparkcommerce.payments.gateways.stripe', [])
        );
    }
}
