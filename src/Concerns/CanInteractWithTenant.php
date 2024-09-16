<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Filament\Facades\Filament;

trait CanInteractWithTenant
{
    protected static function getTenantCurrency()
    {

        $currentTenant = Filament::getTenant();
        $currency = $currentTenant->meta['vendor_currency'];

        if ($currency == null) {
            $currency = config('sparkcommerce.default_currency');
        }

        return $currency;
    }
}
