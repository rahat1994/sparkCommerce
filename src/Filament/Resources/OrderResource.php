<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Rahat1994\SparkCommerce\Concerns\CanInteractWithTenant;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource\Pages;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource\Pages\EditOrder;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource\Pages\ListOrders;
use Rahat1994\SparkCommerce\Models\SCOrder;

class OrderResource extends Resource
{
    use CanInteractWithTenant;

    protected static ?string $model = SCOrder::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = self::getTenantCurrency();
        $isBackoffice = Filament::getCurrentOrDefaultPanel()?->getId() === 'backoffice';

        $columns = [
            TextColumn::make('id')
                ->label('ID'),
            TextColumn::make('tracking_number')
                ->label('Tracking Number'),
            TextColumn::make('total_amount')
                ->label('Order Value')->money($currency),
            TextColumn::make('shipping_status')
                ->label('Shipping Status'),
            TextColumn::make('payment_status')
                ->label('Payment Status'),
            TextColumn::make('status')
                ->label('Status'),
            TextColumn::make('transaction_id')
                ->label('Transaction ID'),
        ];

        if ($isBackoffice) {
            $columns[] = TextColumn::make('vendor.slug')
                ->label('Vendor Slug');
        }

        return $table
            ->columns($columns)
            ->filters([
                //
            ])
            ->recordActions([
                // Tables\Actions\EditAction::make(),
                static::getOrderConfirmActionModal(),
                Action::make('Details')
                    ->action(fn (SCOrder $order) => $order)
                    ->modalWidth('5xl')
                    ->modalContent(fn (SCOrder $record) => view('sparkcommerce::actions.order-confirm-modal', [
                        'order' => $record,
                        'orderContent' => self::getRowItems($record),
                        'currency' => $currency,
                    ])),
                Action::make('cancelOrder')
                    ->label('Cancel Order')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (SCOrder $order) => $order->update([
                        'shipping_status' => 'Cancelled',
                        'status' => 'Cancelled',
                    ])),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getOrderConfirmActionModal()
    {
        return Action::make('Confirm Order')
            ->schema([
                Select::make('shipping_status')
                    ->label('Shipping Status')
                    ->options([
                        'Processing' => 'Processing',
                        'Shipped' => 'Shipped',
                    ])
                    ->required(),
            ])
            ->action(function (array $data, SCOrder $record): void {
                $record->update([
                    'shipping_status' => $data['shipping_status'],
                    'status' => $data['shipping_status'],
                ]);
            })
            ->icon('heroicon-o-information-circle')
            ->label('Accept Order')
            ->color('success')
            ->requiresConfirmation()
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('Confirm Order')
            ->modalCancelActionLabel('Cancel')
            ->modalContent(
                function (SCOrder $record): View {
                    $orderContent = self::getRowItems($record);

                    return view('sparkcommerce::actions.order-confirm-modal', [
                        'order' => $record,
                        'orderContent' => $orderContent,
                        'currency' => self::getTenantCurrency(),
                    ]);
                }
            );
    }

    public static function getRowItems(SCOrder $order): array
    {
        $items = [];

        foreach ($order->items as $item) {
            $productType = $item['itemable_type'];
            $instance = $productType::find($item['itemable_id']);

            $items[] = [
                'id' => $instance->id,
                'name' => $instance->name,
                'quantity' => $item['quantity'],
                'regular_price' => $instance->regular_price,
                'sale_price' => $instance->sale_price,
            ];
        }

        return [
            'id' => $order->id,
            'tracking_number' => $order->tracking_number,
            'total_amount' => $order->total_amount,
            'items' => $items,
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
            'index' => ListOrders::route('/'),
            // 'create' => Pages\CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
