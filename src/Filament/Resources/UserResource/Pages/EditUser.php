<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\UserResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Rahat1994\SparkCommerce\Filament\Resources\UserResource;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $roleName = null;

    protected array $vendorIds = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = method_exists($this->record, 'roles')
            ? $this->record->roles()->value('name')
            : null;

        $data['vendor_ids'] = $this->supportsVendors()
            ? $this->record->vendors()->get()->pluck('id')->all()
            : [];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->roleName = $data['role'] ?? null;
        $this->vendorIds = $data['vendor_ids'] ?? [];

        unset($data['role'], $data['vendor_ids'], $data['password_confirmation'], $data['meta']);

        return $data;
    }

    protected function afterSave(): void
    {
        $supportsRoles = method_exists($this->record, 'syncRoles');
        $supportsVendors = $this->supportsVendors();

        if (! $this->roleName) {
            if ($supportsRoles) {
                $this->record->syncRoles([]);
            }

            if ($supportsVendors) {
                $this->record->vendors()->detach();
            }

            return;
        }

        if ($supportsRoles) {
            $this->record->syncRoles([$this->roleName]);
        }

        if (! $supportsVendors) {
            return;
        }

        if ($this->isVendorOwnerRole()) {
            $this->record->vendors()->sync($this->vendorIds);

            return;
        }

        $this->record->vendors()->detach();
    }

    protected function supportsVendors(): bool
    {
        return UserResource::isMultivendorInstalled()
            && method_exists($this->record, 'vendors');
    }

    protected function isVendorOwnerRole(): bool
    {
        return filled(config('sparkcommerce.vendor_owner_role'))
            && $this->roleName === config('sparkcommerce.vendor_owner_role');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
