<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\CouponResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Rahat1994\SparkCommerce\Concerns\CanSyncResources;
use Rahat1994\SparkCommerce\Filament\Resources\CouponResource;

class CreateCoupon extends CreateRecord
{
    use CanSyncResources;
    protected static string $resource = CouponResource::class;
    protected array $includedProducts = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $this->includedProducts = $data['includedProducts'] ?? [];

        // unset($data['includedProducts']);

        return $data;
    }

    // protected function afterCreate()
    // {
    //     $this->syncResources('includedProducts', $this->includedProducts);
    // }
}
