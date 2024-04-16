<?php

namespace Rahat1994\SparkCommerce\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Rahat1994\SparkCommerce\SparkCommerce
 */
class SparkCommerce extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Rahat1994\SparkCommerce\SparkCommerce::class;
    }
}
