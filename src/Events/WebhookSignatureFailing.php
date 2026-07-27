<?php

namespace Rahat1994\SparkCommerce\Events;

/**
 * Five or more webhook signature verification failures within one clock
 * hour — usually a rotated or misconfigured webhook secret, which means
 * payment notifications are being dropped. Dispatched at most once per
 * hour window. The admin alert listener arrives in U14; this event is the
 * seam.
 */
class WebhookSignatureFailing
{
    public function __construct(
        public string $gateway,
        public int $failuresThisHour,
    ) {}
}
