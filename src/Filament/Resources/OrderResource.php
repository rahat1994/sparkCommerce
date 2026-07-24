<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Rahat1994\SparkCommerce\Concerns\CanInteractWithTenant;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Enums\PaymentStatus;
use Rahat1994\SparkCommerce\Exceptions\IllegalOrderTransition;
use Rahat1994\SparkCommerce\Exceptions\RefundGatewayFailed;
use Rahat1994\SparkCommerce\Exceptions\RefundNotAllowed;
use Rahat1994\SparkCommerce\Filament\Concerns\HasSparkCommercePanelAccess;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource\Pages;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource\Pages\EditOrder;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource\Pages\ListOrders;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Payments\Exceptions\PaymentCancellationRefused;
use Rahat1994\SparkCommerce\Payments\PaymentGatewayManager;
use Rahat1994\SparkCommerce\Services\OrderTransitionService;
use Rahat1994\SparkCommerce\Services\RefundService;

class OrderResource extends Resource
{
    use CanInteractWithTenant;
    use HasSparkCommercePanelAccess;

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
                ->label('Order Value')
                // Prefer the integer-cent column; legacy rows without a
                // cents value fall back to the untouched major-unit column.
                ->state(fn (SCOrder $record): mixed => $record->total_amount_cents !== null
                    ? ((int) $record->total_amount_cents->getAmount()) / 100
                    : $record->total_amount)
                ->money(fn (SCOrder $record) => $record->currency ?? $currency),
            TextColumn::make('shipping_status')
                ->label('Shipping Status'),
            TextColumn::make('payment_status')
                ->label('Payment Status')
                ->badge(),
            TextColumn::make('status')
                ->label('Status')
                ->badge(),
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
                    // Only an awaiting-payment order may be cancelled here: a
                    // Paid order is money that must be REFUNDED (which
                    // auto-cancels via the refund -> Refunded path), never
                    // stranded in Cancelled where Refund is hidden.
                    ->visible(fn (SCOrder $record): bool => $record->status === OrderStatus::AwaitingPayment)
                    ->action(fn (SCOrder $order) => static::cancelOrder($order)),
                static::getRefundAction(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Cancel an awaiting-payment order. The outstanding gateway payment is
     * cancelled FIRST, so a live PaymentIntent is never orphaned by the
     * order moving to Cancelled. A processor that refuses (e.g. the payment
     * is already processing) surfaces a danger notification and the order is
     * LEFT alone — never cancel an order whose charge could not be cancelled.
     * Free orders (no gateway) skip the gateway entirely.
     */
    public static function cancelOrder(SCOrder $order): void
    {
        if ($order->payment_gateway !== null) {
            try {
                app(PaymentGatewayManager::class)
                    ->driver((string) $order->payment_gateway)
                    ->cancelPayment($order);
            } catch (PaymentCancellationRefused $exception) {
                Notification::make()
                    ->title('Order was not cancelled')
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();

                return;
            }
        }

        static::transitionOrder($order, OrderStatus::Cancelled);
    }

    /**
     * Route an order through the transition service, surfacing illegal
     * moves as a Filament notification instead of a crash.
     *
     * @param  array{payment_status?: PaymentStatus|string|null}  $context
     */
    public static function transitionOrder(SCOrder $order, OrderStatus $to, array $context = []): void
    {
        try {
            app(OrderTransitionService::class)->transition($order, $to, $context);
        } catch (IllegalOrderTransition $exception) {
            Notification::make()
                ->title('Order status was not changed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Admin refund (R14): full or partial, routed through the RefundService
     * (which owns the over-refund guard, the gateway call and the derived
     * statuses). Visible for orders holding refundable money — Paid or
     * Processing, including a PartiallyRefunded payment state with a
     * remaining balance (partial refunds leave `status` at Paid/Processing).
     *
     * KTD18: visibility is NOT authorization — the action is both
     * `->authorize`d on the panel gate and the gate is re-checked inside
     * the handler before any money moves.
     */
    public static function getRefundAction(): Action
    {
        $restockByDefault = fn (): bool => (bool) config('sparkcommerce.refunds.restock_by_default', true);

        return Action::make('refund')
            ->label('Refund')
            ->icon('heroicon-o-receipt-refund')
            ->color('warning')
            ->visible(fn (SCOrder $record): bool => in_array($record->status, [OrderStatus::Paid, OrderStatus::Processing], true))
            ->authorize(fn (): bool => Gate::allows('access-sparkcommerce-admin'))
            ->schema([
                TextInput::make('amount')
                    ->label('Amount to refund')
                    ->helperText('In major units, e.g. 19.99. Defaults to the remaining refundable balance.')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->maxValue(fn (SCOrder $record): float => static::remainingRefundableCents($record) / 100)
                    ->default(fn (SCOrder $record): float => static::remainingRefundableCents($record) / 100)
                    ->suffix(fn (SCOrder $record): string => (string) $record->currency)
                    ->live(onBlur: true)
                    // Restocking only makes sense when the WHOLE remaining
                    // balance goes back: an edited (partial) amount unchecks
                    // the restock default.
                    ->afterStateUpdated(function (mixed $state, Set $set, SCOrder $record) use ($restockByDefault): void {
                        $set('restock', $restockByDefault()
                            && (int) round(((float) $state) * 100) === static::remainingRefundableCents($record));
                    }),
                Checkbox::make('restock')
                    ->label('Restock the order items')
                    ->default($restockByDefault),
            ])
            ->action(function (array $data, SCOrder $record): void {
                // Visibility is not authorization (KTD18): re-check at the
                // execution boundary.
                Gate::authorize('access-sparkcommerce-admin');

                try {
                    app(RefundService::class)->refund(
                        $record,
                        (int) round(((float) $data['amount']) * 100),
                        initiatedBy: auth()->id() !== null ? (int) auth()->id() : null,
                        restock: (bool) ($data['restock'] ?? false),
                    );

                    Notification::make()
                        ->title('Refund processed')
                        ->success()
                        ->send();
                } catch (RefundGatewayFailed | RefundNotAllowed $exception) {
                    Notification::make()
                        ->title('Refund was not processed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Cents still refundable, pending refunds included (delegated to the
     * RefundService so the form default/max and the service guard can
     * never disagree).
     */
    public static function remainingRefundableCents(SCOrder $order): int
    {
        return app(RefundService::class)->remainingRefundableCents($order);
    }

    public static function getOrderConfirmActionModal()
    {
        return Action::make('Confirm Order')
            ->schema([
                Select::make('status')
                    ->label('New Status')
                    ->options([
                        OrderStatus::Processing->value => OrderStatus::Processing->getLabel(),
                        OrderStatus::Shipped->value => OrderStatus::Shipped->getLabel(),
                    ])
                    ->required(),
            ])
            ->action(function (array $data, SCOrder $record): void {
                static::transitionOrder($record, OrderStatus::from($data['status']));
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

        foreach ($order->items ?? [] as $item) {
            // Orders created after the snapshot change carry the purchase
            // data inline; read it instead of the live product so deleted
            // products cannot break (or rewrite) history.
            if (isset($item['unit_amount']) || isset($item['name'])) {
                $items[] = [
                    'id' => $item['itemable_id'] ?? null,
                    'name' => $item['name'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'regular_price' => isset($item['unit_amount']) ? $item['unit_amount'] / 100 : null,
                    'sale_price' => null,
                ];

                continue;
            }

            $productType = $item['itemable_type'];
            $instance = $productType::find($item['itemable_id']);

            $items[] = [
                'id' => $instance?->id ?? ($item['itemable_id'] ?? null),
                'name' => $instance?->name,
                'quantity' => $item['quantity'],
                'regular_price' => $instance?->regular_price,
                'sale_price' => $instance?->sale_price,
            ];
        }

        return [
            'id' => $order->id,
            'tracking_number' => $order->tracking_number,
            // Cents column first; legacy rows fall back to the major-unit
            // decimal.
            'total_amount' => $order->total_amount_cents !== null
                ? ((int) $order->total_amount_cents->getAmount()) / 100
                : $order->total_amount,
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
