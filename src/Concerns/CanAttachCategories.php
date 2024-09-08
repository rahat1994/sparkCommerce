<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Rahat1994\SparkCommerce\Models\SCCategory;

trait CanAttachCategories
{
    protected array $product_categories = [];

    protected function attachCategories()
    {
        $this->product_categories = array_unique($this->product_categories);

        // Retrieve existing categories
        $existingCategories = $this->record->categories->pluck('id')->toArray();

        // Determine categories to add and remove
        $categoriesToAdd = array_diff($this->product_categories, $existingCategories);
        $categoriesToRemove = array_diff($existingCategories, $this->product_categories);

        foreach ($this->product_categories as $categoryId) {
            $this->assignCategoryToProduct($categoryId, $this->record->id);
        }

        // Detach removed categories
        foreach ($categoriesToRemove as $categoryId) {
            $this->removeCategoryFromProduct($categoryId, $this->record->id);
        }
    }


    public function assignCategoryToProduct($categoryId, $recordId)
    {
        $this->record->categories()->attach($categoryId);
    }

    public function removeCategoryFromProduct($categoryId, $recordId)
    {
        $this->record->categories()->detach($categoryId);
    }
}
