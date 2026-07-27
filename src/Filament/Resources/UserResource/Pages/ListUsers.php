<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\UserResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Rahat1994\SparkCommerce\Filament\Resources\UserResource;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    // public function getTabs(): array
    // {
    //     return [
    //         'All' => Tab::make(),
    //         'Trashed' => Tab::make(),
    //     ];
    // }
}
