<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ReviewResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Rahat1994\SparkCommerce\Filament\Resources\ReviewResource;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
