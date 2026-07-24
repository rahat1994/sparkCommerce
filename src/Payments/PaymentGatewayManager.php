<?php

namespace Rahat1994\SparkCommerce\Payments;

use Illuminate\Support\Manager;
use Rahat1994\SparkCommerce\Payments\Contracts\PaymentGateway;
use Rahat1994\SparkCommerce\Payments\Drivers\FakeGateway;
use Rahat1994\SparkCommerce\Payments\Drivers\StripeGateway;

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
        return new FakeGateway;
    }

    protected function createStripeDriver(): PaymentGateway
    {
        return new StripeGateway(
            (array) $this->config->get('sparkcommerce.payments.gateways.stripe', [])
        );
    }
}
