<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
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
                TextColumn::make('id')
                    ->label('ID'),
                TextColumn::make('tracking_number')
                    ->label('Tracking Number'),
                TextColumn::make('total_amount')
                    ->label('Order Value'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                self::getOrderConfirmActionModal(),
                Action::make('cancelOrder')
                    ->label('Cancel Order')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (SCOrder $order) => $order->delete()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getOrderConfirmActionModal()
    {
        return Action::make('Confirm Order')
            ->form([
                Select::make('shipping_status')
                    ->label('Shipping Status')
                    ->options([
                        1 => 'Processing',
                        2 => 'Shipped',
                    ])
                    ->required(),
            ])
            ->action(function (array $data, SCOrder $record): void {
                // $record->author()->associate($data['authorId']);
                // $record->save();
            })
            ->label('Confirm Order')
            ->color('success')
            ->requiresConfirmation()->modalContent(
                fn (SCOrder $record): View => view('sparkcommerce::actions.order-confirm-modal', ['record' => $record])
            );
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
