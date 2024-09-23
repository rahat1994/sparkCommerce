<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Filament\Facades\Filament;

trait CanInteractWithTenant
{
    protected static function getTenantCurrency()
    {

        $currentTenant = Filament::getTenant();
        
        if (!$currentTenant) {
            return config('sparkcommerce.default_currency');
        }

        $currency = array_key_exists('vendor_currency', $currentTenant->meta)
            ? $currentTenant->meta['vendor_currency']
            : config('sparkcommerce.default_currency');

        return $currency;
    }
}
