<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ReviewResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Rahat1994\SparkCommerce\Filament\Resources\ReviewResource;

class EditReview extends EditRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
