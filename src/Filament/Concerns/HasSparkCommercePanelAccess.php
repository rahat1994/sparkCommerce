<?php

namespace Rahat1994\SparkCommerce\Filament\Concerns;

use Illuminate\Support\Facades\Gate;

trait HasSparkCommercePanelAccess
{
    public static function canAccess(): bool
    {
        return Gate::allows('access-sparkcommerce-admin');
    }
}
