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
        // dd($data);
        $data['slug'] = Str::slug($data['name']);

        return $data;
    }

    public function saveCategory($categoryName, $parentId)
    {
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
