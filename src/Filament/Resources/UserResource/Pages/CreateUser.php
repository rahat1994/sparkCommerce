<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\UserResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Rahat1994\SparkCommerce\Filament\Resources\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $roleName = null;

    protected array $vendorIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->roleName = $data['role'] ?? null;
        $this->vendorIds = $data['vendor_ids'] ?? [];

        unset($data['role'], $data['vendor_ids'], $data['password_confirmation'], $data['meta']);

        return $data;
    }

    public function afterCreate(): void
    {
        if (! $this->roleName) {
            return;
        }

        $user = $this->record;

        if (method_exists($user, 'assignRole')) {
            $user->assignRole($this->roleName);
        }

        if ($this->isVendorOwnerRole() && method_exists($user, 'vendors')) {
            $user->vendors()->syncWithoutDetaching($this->vendorIds);
        }
    }

    protected function isVendorOwnerRole(): bool
    {
        return UserResource::isMultivendorInstalled()
            && filled(config('sparkcommerce.vendor_owner_role'))
            && $this->roleName === config('sparkcommerce.vendor_owner_role');
    }
}
