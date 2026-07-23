<?php

namespace Rahat1994\SparkCommerce\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

class AllowEveryonePanelGate
{
    public function __invoke(?Authenticatable $user): bool
    {
        return true;
    }
}
