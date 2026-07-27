<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Rahat1994\SparkCommerce\Filament\Concerns\HasSparkCommercePanelAccess;
use Rahat1994\SparkCommerce\Filament\Resources\UserResource\Pages\CreateUser;
use Rahat1994\SparkCommerce\Filament\Resources\UserResource\Pages\EditUser;
use Rahat1994\SparkCommerce\Filament\Resources\UserResource\Pages\ListUsers;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Actions\Impersonate;

class UserResource extends Resource
{
    use HasSparkCommercePanelAccess;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    public static function getModel(): string
    {
        return config('auth.providers.users.model');
    }

    public static function getModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.user.model_label');
        // return __('filament-user-activity::user-activity.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.user.model_plural_label');
        // return __('filament-user-activity::user-activity.resource.model_plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('sparkcommerce::sparkcommerce.resource.user.navigation_group');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function getNavigationLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.user.navigation');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::getUserFields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(fn (string $state): string => $state)
                    ->searchable(),
                TextColumn::make('email')
                    ->formatStateUsing(fn (string $state): string => $state)
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Impersonate::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getUserFields(bool $includeRoleFields = true)
    {
        $fields = [
            TextInput::make('name')
                ->label(__('sparkcommerce::sparkcommerce.resource.user.creation_form.name'))
                ->required(),
            TextInput::make('email')
                ->label(__('sparkcommerce::sparkcommerce.resource.user.creation_form.email'))
                ->email()
                ->unique(ignoreRecord: true)
                ->required(),
            TextInput::make('password')
                ->label(__('sparkcommerce::sparkcommerce.resource.user.creation_form.password'))
                ->password()
                ->required()
                ->minLength(8),
            TextInput::make('password_confirmation')
                ->label(__('sparkcommerce::sparkcommerce.resource.user.creation_form.password_confirmation'))
                ->password()
                ->same('password')
                ->required()
                ->minLength(8),
            KeyValue::make('meta')
                ->label(__('sparkcommerce::sparkcommerce.resource.user.creation_form.meta'))->columnSpan(2),
        ];

        if (! $includeRoleFields) {
            return $fields;
        }

        $fields[] = Select::make('role')
            ->label(__('sparkcommerce::sparkcommerce.resource.user.creation_form.role'))
            ->options(
                Role::all()->mapWithKeys(fn (Role $role): array => [$role->name => $role->name])
            )
            ->live()
            ->columnSpan(2);

        if (static::isMultivendorInstalled()) {
            $isVendorOwnerRole = fn (Get $get): bool => filled(config('sparkcommerce.vendor_owner_role'))
                && $get('role') === config('sparkcommerce.vendor_owner_role');

            $fields[] = Select::make('vendor_ids')
                ->label('Vendors')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(function (): array {
                    /** @var class-string<Model> $vendorModel */
                    $vendorModel = config('sparkcommerce.vendor_model');

                    return $vendorModel::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all();
                })
                ->visible($isVendorOwnerRole)
                ->required($isVendorOwnerRole)
                ->columnSpan(2);
        }

        return $fields;
    }

    /**
     * Detect the multivendor package through config instead of a fragile
     * facade-alias check: the configured vendor model must exist.
     */
    public static function isMultivendorInstalled(): bool
    {
        $vendorModel = config('sparkcommerce.vendor_model');

        return $vendorModel !== null && class_exists($vendorModel);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
