<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Rahat1994\SparkCommerce\Filament\Concerns\HasSparkCommercePanelAccess;
use Rahat1994\SparkCommerce\Filament\Resources\TagResource\Pages\CreateTag;
use Rahat1994\SparkCommerce\Filament\Resources\TagResource\Pages\EditTag;
use Rahat1994\SparkCommerce\Filament\Resources\TagResource\Pages\ListTags;
use Rahat1994\SparkCommerce\Models\SCTag;

class TagResource extends Resource
{
    use HasSparkCommercePanelAccess;

    protected static ?string $model = SCTag::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    public static function getModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.tag.model_label');
        // return __('filament-user-activity::user-activity.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.tag.model_plural_label');
        // return __('filament-user-activity::user-activity.resource.model_plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('sparkcommerce::sparkcommerce.resource.tag.navigation_group');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function getNavigationLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.tag.navigation');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(fn (string $state): string => $state),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListTags::route('/'),
            'create' => CreateTag::route('/create'),
            'edit' => EditTag::route('/{record}/edit'),
        ];
    }
}
