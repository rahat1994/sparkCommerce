<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\UserResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Rahat1994\SparkCommerce\Filament\Resources\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function afterCreate()
    {
        $data = $this->form->getState();
        if (isset($data['role']) && $data['role'] === config('sparkcommerce-multivendor.vendor_owner_role')) {
            // Do something

            $user = $this->record;
            $user->assignRole(config('sparkcommerce-multivendor.vendor_owner_role'));
        }
    }
}
