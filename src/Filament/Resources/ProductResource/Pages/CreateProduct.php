<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Rahat1994\SparkCommerce\Concerns\CanAttachCategories;
use Rahat1994\SparkCommerce\Concerns\CanCreateCategories;
use Rahat1994\SparkCommerce\Concerns\CanPersistVariations;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;

class CreateProduct extends CreateRecord
{
    use CanAttachCategories;
    use CanCreateCategories;
    use CanPersistVariations;

    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $this->product_categories = $data['product_categories'] ?? [];

        return $this->extractVariations($data);
    }

    protected function afterCreate()
    {
        $this->attachCategories();
        $this->persistVariations();
    }
}
