<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Rahat1994\SparkCommerce\Concerns\CanAttachCategories;
use Rahat1994\SparkCommerce\Concerns\CanCreateCategories;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;
use Rahat1994\SparkCommerce\Models\SCCategory;

class CreateProduct extends CreateRecord
{
    use CanCreateCategories, CanAttachCategories;


    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['price'] = $data['regular_price'];
        $this->product_categories = $data['product_categories'] ?? null;

        return $data;
    }

    protected function afterCreate()
    {
        $this->attachCategories();
    }
}
