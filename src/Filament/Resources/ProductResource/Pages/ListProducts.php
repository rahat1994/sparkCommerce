<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;
use Filament\Actions;

class ListProducts extends ListRecords
{
    public static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()->recordUrl(null);
    }
}
