<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\TagResource\Pages;

use Rahat1994\SparkCommerce\Filament\Resources\TagResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
