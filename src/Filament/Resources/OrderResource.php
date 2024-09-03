<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Tables\Actions\Action;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource\Pages;
use Rahat1994\SparkCommerce\Models\SCOrder;

class OrderResource extends Resource
{
    protected static ?string $model = SCOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function getModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.order.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.order.model_plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('sparkcommerce::sparkcommerce.resource.order.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.order.navigation');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('Confirm Order')
                    ->label('Confirm Order')
                    ->message('Are you sure you want to confirm this order?')
                    ->backgroundColor('bg-green-500')
                    ->confirmText('Yes, Confirm Order')
                    ->cancelText('No, Keep Order')
                    ->handler(fn (SCOrder $order) => $order->update(['status' => 'confirmed'])),
                Action::make('cancelOrder')
                    ->label('Cancel Order')
                    ->message('Are you sure you want to cancel this order?')
                    ->confirmText('Yes, Cancel Order')
                    ->backgroundColor('bg-red-500')
                    ->cancelText('No, Keep Order')
                    ->handler(fn (SCOrder $order) => $order->delete()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
