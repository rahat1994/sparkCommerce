<?php

namespace Rahat1994\SparkCommerce\Concerns;

trait CanSyncResources
{
    protected function syncResources(string $relation, array $resources)
    {
        $this->record->{$relation}()->sync(array_unique($resources));
    }
}
