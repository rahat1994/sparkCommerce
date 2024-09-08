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

        foreach ($this->product_categories as $categoryId) {
            $this->assignCategoryToProduct($categoryId, $this->record->id);
        }
    }

    public function assignCategoryToProduct($categoryId, $recordId)
    {
        DB::table(
            strval(config('sparkcommerce.table_prefix')) . strval(config('sparkcommerce.category_product_table_name'))
        )->insert([
            'category_id' => $categoryId,
            'product_id' => $recordId,
        ]);
    }
}
