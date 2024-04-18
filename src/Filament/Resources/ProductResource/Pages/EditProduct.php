<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;

class EditProduct extends EditRecord
{
    public static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
