<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Rahat1994\SparkCommerce\Models\SCCategory;

trait CanCreateCategories
{

    public function saveCategory($categoryName, $parentId)
    {
        $category = new SCCategory();

        $category->name = $categoryName;
        $category->parent_id = ($parentId) ? $parentId : null;
        $category->user_id = auth()->user()->id;
        $category->vendor_id = Filament::getTenant()->id;
        $category->save();

        $this->data['product_categories'][] = strval($category->id);
        $this->data['product_categories'] = array_unique($this->data['product_categories']);

        $this->dispatch('category-created', id: $category->id);

        return $category;
    }
}
