<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Rahat1994\SparkCommerce\Filament\Resources\CategoryResource\Pages\CreateCategory;
use Rahat1994\SparkCommerce\Filament\Resources\CategoryResource\Pages\EditCategory;
use Rahat1994\SparkCommerce\Filament\Resources\CategoryResource\Pages\ListCategories;
use Rahat1994\SparkCommerce\Models\SCCategory;

class CategoryResource extends Resource
{
    protected static ?string $model = SCCategory::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bars-4';

    public static function getModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.category.model_label');
        // return __('filament-user-activity::user-activity.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.category.model_plural_label');
        // return __('filament-user-activity::user-activity.resource.model_plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('sparkcommerce::sparkcommerce.resource.category.navigation_group');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function getNavigationLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.category.navigation');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('sparkcommerce::sparkcommerce.resource.category.creation_form.name'))
                    ->required(),
                Select::make('parent_id')
                    ->label(__('sparkcommerce::sparkcommerce.resource.category.creation_form.parent_category'))
                    ->options(
                        SCCategory::query()
                            ->get()
                            ->mapWithKeys(fn ($category) => [$category->id => $category->name])
                    )
                    ->placeholder(__('sparkcommerce::sparkcommerce.resource.category.creation_form.parent_category')),
                Hidden::make('user_id')
                    ->default(auth()->id()),

            ]);
    }

    public static function table(Table $table): Table
    {
        // TODO: Fix the filtering and searching
        // TODO: Fix how the categories are loaded in the table.   //
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->label(__('sparkcommerce::sparkcommerce.resource.category.creation_form.name')),
                TextColumn::make('parent.name')
                    ->label(__('sparkcommerce::sparkcommerce.resource.category.creation_form.parent_category')),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->relationship('parent', 'name')
                    ->label(__('sparkcommerce::sparkcommerce.resource.category.creation_form.parent_category'))
                    ->query(function (Builder $query, BaseFilter $filter) {
                        $state = $filter->getState();
                        if (isset($state['value']) && $state['value'] != null) {
                            return $query->where('parent_id', $filter->getState());
                        }

                        return $query;
                    }),

            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
