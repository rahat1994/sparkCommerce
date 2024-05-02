<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\TagResource\Pages;

use Rahat1994\SparkCommerce\Filament\Resources\TagResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
