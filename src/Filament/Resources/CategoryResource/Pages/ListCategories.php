<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\CategoryResource\Pages;

use Filament\Resources\Components\Tab;
use Rahat1994\SparkCommerce\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'All' => Tab::make(),
            'Trashed' => Tab::make(),
        ];
    }
}
