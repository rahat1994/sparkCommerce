<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Rahat1994\SparkCommerce\Concerns\CanAttachCategories;
use Rahat1994\SparkCommerce\Concerns\CanCreateCategories;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;

class CreateProduct extends CreateRecord
{
    use CanAttachCategories;
    use CanCreateCategories;

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
