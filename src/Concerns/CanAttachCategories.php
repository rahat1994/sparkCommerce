<?php

namespace Rahat1994\SparkCommerce\Concerns;

trait CanAttachCategories
{
    protected array $product_categories = [];

    protected function attachCategories()
    {
        $this->record->categories()->sync(array_unique($this->product_categories));
    }
}
