<?php

use Rahat1994\SparkCommerce\Payments\Drivers\FakeGateway;
use Rahat1994\SparkCommerce\Payments\PaymentGatewayManager;

it('resolves the fake driver in the testing environment', function () {
    expect(app(PaymentGatewayManager::class)->driver('fake'))
        ->toBeInstanceOf(FakeGateway::class);
});

it('refuses to resolve the fake driver outside testing/local', function () {
    $original = app()['env'];
    app()['env'] = 'production';

    try {
        expect(fn () => (new PaymentGatewayManager(app()))->driver('fake'))
            ->toThrow(RuntimeException::class, 'cannot be used outside');
    } finally {
        // Restore before the RefreshDatabase teardown, which would otherwise
        // hit Laravel's production-confirmation prompt on rollback.
        app()['env'] = $original;
    }
});
