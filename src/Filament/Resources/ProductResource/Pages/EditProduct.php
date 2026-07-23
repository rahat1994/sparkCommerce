<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Rahat1994\SparkCommerce\Concerns\CanAttachCategories;
use Rahat1994\SparkCommerce\Concerns\CanCreateCategories;
use Rahat1994\SparkCommerce\Concerns\CanPersistVariations;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;

class EditProduct extends EditRecord
{
    use CanAttachCategories;
    use CanCreateCategories;
    use CanPersistVariations;

    public static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        if (static::hasMacro('getAdditionalHeaderActions')) {
            return $this->getAdditionalHeaderActions();
        }

        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillVariations($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['user_id'] = auth()->user()->id;
        if (isset($data['product_categories'])) {
            $this->product_categories = $data['product_categories'];
        }

        return $this->extractVariations($data);
    }

    public function afterSave()
    {
        $this->attachCategories();
        $this->persistVariations();
    }
}
