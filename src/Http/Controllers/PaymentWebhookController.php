<?php

namespace Rahat1994\SparkCommerce\Http\Controllers;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Rahat1994\SparkCommerce\Payments\Contracts\PaymentGateway;
use Rahat1994\SparkCommerce\Payments\PaymentGatewayManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic gateway notification entry: POST sc/webhooks/{gateway}.
 *
 * The route is loaded middleware-free (package-tools `loadRoutesFrom`), so
 * no CSRF or session ever applies — processors POST raw signed payloads.
 * The controller only resolves the driver; VERIFICATION is entirely the
 * driver's job ({@see PaymentGateway::handleWebhook()}),
 * and every driver must default-deny.
 */
class PaymentWebhookController
{
    public function __invoke(Request $request, string $gateway): Response
    {
        try {
            $driver = app(PaymentGatewayManager::class)->driver($gateway);
        } catch (InvalidArgumentException) {
            // Unknown gateway: indistinguishable from any other missing URL.
            abort(404);
        }

        return $driver->handleWebhook($request);
    }
}
