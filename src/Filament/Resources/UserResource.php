<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use App\Models\User;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Rahat1994\SparkCommerce\Filament\Resources\UserResource\Pages;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema(static::getUserFields());
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Impersonate::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getUserFields()
    {
        return [
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
            Select::make('role')
                ->label(__('sparkcommerce::sparkcommerce.resource.user.creation_form.role'))
                ->options(
                    Role::all()->mapWithKeys(fn (Role $role): array => [$role->name => $role->name])
                )->columnSpan(2),
        ];
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
