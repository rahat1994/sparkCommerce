<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\CouponResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Rahat1994\SparkCommerce\Filament\Resources\CouponResource;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
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
