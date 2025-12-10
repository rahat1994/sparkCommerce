<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Rahat1994\SparkCommerce\Concerns\CanAttachCategories;
use Rahat1994\SparkCommerce\Concerns\CanCreateCategories;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;

class EditProduct extends EditRecord
{
    use CanAttachCategories;
    use CanCreateCategories;

    public static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        if (static::hasMacro('getAdditionalHeaderActions')) {
            return $this->getAdditionalHeaderActions();
        }
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['user_id'] = auth()->user()->id;
        if (isset($data['product_categories'])) {
            $this->product_categories = $data['product_categories'];
        }

        return $data;
    }

    public function afterSave()
    {
        $this->attachCategories();
    }
}
