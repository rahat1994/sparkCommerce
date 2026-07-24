<?php

use Illuminate\Support\Facades\Route;
use Rahat1994\SparkCommerce\Http\Controllers\PaymentWebhookController;

/*
 * Payment gateway notification entry (R13). Registered through
 * package-tools' hasRoutes(), which loads this file via loadRoutesFrom —
 * OUTSIDE every middleware group, so no CSRF token and no session apply.
 * Signature verification inside the driver is the only gate, exactly as a
 * processor-to-server endpoint requires.
 */
Route::post('sc/webhooks/{gateway}', PaymentWebhookController::class)
    ->name('sparkcommerce.webhooks.gateway');
