<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Filament\Facades\Filament;
use Rahat1994\SparkCommerce\Models\SCCategory;

trait CanCreateCategories
{
    public function saveCategory($categoryName, $parentId)
    {
        $category = SCCategory::create([
            'name' => $categoryName,
            'parent_id' => $parentId ?: null,
            'user_id' => auth()->id(),
            'vendor_id' => Filament::getTenant()->id,
        ]);

        $this->data['product_categories'] = array_unique(
            array_merge($this->data['product_categories'], [strval($category->id)])
        );

        $this->dispatch('category-created', id: $category->id);

        return $category;
    }
}
