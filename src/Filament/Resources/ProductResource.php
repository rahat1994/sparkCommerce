<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

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
