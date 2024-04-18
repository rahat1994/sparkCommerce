<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Tables\Table;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;
use Filament\Actions;
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