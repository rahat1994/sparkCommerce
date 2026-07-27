<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\CouponResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Rahat1994\SparkCommerce\Filament\Resources\CouponResource;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
