<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;

class ListProducts extends ListRecords
{
    public static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()->recordUrl(null);
    }
}
