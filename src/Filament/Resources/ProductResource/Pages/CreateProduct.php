<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;
use Rahat1994\SparkCommerce\Models\SCCategory;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        dd($data);
        $data['slug'] = Str::slug($data['name']);

        return $data;
    }

    public function saveCategory($categoryName, $parentId)
    {

        // TODO: inplement try/catch block.
        // TODO: implement validation for category name and parent id.
        // TODO: Handle the case when the category already exists.
        // TODO: Handle the case when there are no category to be assigned as parent_id
        
        $category = new SCCategory();

        $category->name = $categoryName;
        $category->parent_id = $parentId;
        $category->user_id = auth()->user()->id;
        $category->save();

        $this->data['product_categories'][] = strval($category->id);
        $this->data['product_categories'] = array_unique($this->data['product_categories']);

        $this->dispatch('category-created', id: $category->id);

        return $category;
    }
}
