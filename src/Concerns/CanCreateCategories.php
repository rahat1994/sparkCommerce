<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Filament\Facades\Filament;
use Rahat1994\SparkCommerce\Models\SCCategory;

trait CanCreateCategories
{
    // TODO: Modify this method to not include any vendor specific code
    // TODO: You can use event listeners to handle vendor specific code

    public function saveCategory($categoryName, $parentId)
    {
        $vendor = Filament::getTenant();

        $category = SCCategory::create([
            'name' => $categoryName,
            'parent_id' => $parentId ?: null,
            'user_id' => auth()->id(),
        ]);

        if ($vendor) {
            $category->vendor_id = $vendor->id;
            $category->save();
        }

        $this->data['product_categories'] = array_unique(
            array_merge($this->data['product_categories'], [strval($category->id)])
        );

        $this->dispatch('category-created', id: $category->id);

        return $category;
    }
}
