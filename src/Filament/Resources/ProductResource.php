<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\CreateProduct;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\EditProduct;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\ListProducts;
use Rahat1994\SparkCommerce\Models\SCProduct;

class ProductResource extends Resource
{
    protected static ?string $model = SCProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.product.model_label');
        // return __('filament-user-activity::user-activity.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.product.model_plural_label');
        // return __('filament-user-activity::user-activity.resource.model_plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('sparkcommerce::sparkcommerce.resource.product.navigation_group');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function getNavigationLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.product.navigation');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->searchable(true)
            ->actions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_name')),
                RichEditor::make('description')
                    ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.description')),
                self::getProductDimensionFields(),

            ])->columns(1);
    }

    public static function getProductDimensionFields()
    {
        return Fieldset::make('product_dimensions')
            ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.fieldset_name'))
            ->schema([
                TextInput::make('width')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.weight')),
                TextInput::make('height')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.height')),
                TextInput::make('width')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.width')),
            ])->columns(3);
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
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
