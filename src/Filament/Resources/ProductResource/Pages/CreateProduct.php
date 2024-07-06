<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;
use Rahat1994\SparkCommerce\Models\SCCategory;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected array $product_categories = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // dd($this->record->id);
        // $data['slug'] = Str::slug($data['name']);
        $data['user_id'] = auth()->user()->id;
        $data['price'] = $data['regular_price'];

        if (isset($data['product_categories'])) {
            $this->product_categories = $data['product_categories'];
            // unset($data['product_categories']);
        }

        return $data;
    }

    protected function afterCreate()
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
