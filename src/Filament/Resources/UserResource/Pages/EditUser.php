<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\UserResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Rahat1994\SparkCommerce\Filament\Resources\UserResource;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $roleName = null;

    protected array $vendorIds = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles()->value('name');
        $data['vendor_ids'] = $this->record->vendors()->pluck('sc_mv_vendors.id')->all();

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
        if (! $this->roleName) {
            $this->record->syncRoles([]);
            $this->record->vendors()->detach();

            return;
        }

        $this->record->syncRoles([$this->roleName]);

        if ($this->roleName === config('sparkcommerce-multivendor.vendor_owner_role')) {
            $this->record->vendors()->sync($this->vendorIds);

            return;
        }

        $this->record->vendors()->detach();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
