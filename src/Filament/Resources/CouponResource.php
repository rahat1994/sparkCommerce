<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Rahat1994\SparkCommerce\Filament\Resources\CouponResource\Pages;
use Rahat1994\SparkCommerce\Models\SCCoupon;

class CouponResource extends Resource
{
    protected static ?string $model = SCCoupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-4';

    public static function getModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.coupon.model_label');
        // return __('filament-user-activity::user-activity.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.coupon.model_plural_label');
        // return __('filament-user-activity::user-activity.resource.model_plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('sparkcommerce::sparkcommerce.resource.coupon.navigation_group');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function getNavigationLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.coupon.navigation');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('coupon_code')
                    ->label(__('sparkcommerce::sparkcommerce.resource.coupon.creation_form.name'))
                    ->required(),
                Tabs::make('coupon_data')
                    ->label(__('sparkcommerce::sparkcommerce.resource.coupon.creation_form.coupon_data'))
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->schema(self::generalTabContent()),
                        Tabs\Tab::make('Usage restriction')
                            ->schema(self::usageRestrictionTabContent()),
                        Tabs\Tab::make('Usage limits')
                            ->schema(self::usageLimitTabContent()),
                        Tabs\Tab::make('Giveaway products')
                            ->schema([]),
                        Tabs\Tab::make('Exclusions')
                            ->schema(self::exclusions()),
                    ]),
            ])->columns(1);
    }

    public static function generalTabContent(): array
    {
        return [
            Select::make('coupon_type')
                ->options([
                    'percentage_discount' => 'Percentage Discount',
                    'fixed_cart_discount' => 'Fixed Cart Discount',
                ]),
            TextInput::make('coupon_amount')->numeric(),
            DatePicker::make('end_date'),
            DatePicker::make('start_date'),
            Radio::make('number_of_uses')
                ->options([
                    0 => 'Apply once',
                    1 => 'Apply repeatedly',
                ])->helperText(
                    new HtmlString('<br><strong>Apply once</strong>: If cart is eligible or conditions are met, coupon applies once. ie: If you set the coupon to offer Buy 2 Get 1, you get one free product. Moving more items to the cart will not make it eligible to get more free products.<br><br> <strong>Apply repeatedly:</strong> The coupon applies repeatedly whenever the cart is eligible or if conditions are met. ie: If you set the coupon to offer Buy 2 Get 1 then the coupon works repeatedly for Buy 4 Get 2 and so on.')
                ),
        ];
    }

    public static function usageRestrictionTabContent(): array
    {
        return [
            TextInput::make('min_spend')->numeric(),
            TextInput::make('max_spend')->numeric(),
            Checkbox::make('individual_use')->helperText('Check this box if the coupon cannot be used in conjunction with other coupons.'),
            Checkbox::make('exclude_sale_items')->helperText('Check this box if the coupon cannot be used on sale items.'),

        ];
    }

    public static function usageLimitTabContent(): array
    {
        return [
            TextInput::make('usage_limit')->numeric()->helperText('How many times this coupon can be used before it is void.'),
            TextInput::make('usage_limit_per_user')->numeric()->helperText('How many times this coupon can be used by each user before it is void.'),
        ];
    }

    public static function exclusions(): array
    {
        return [

            TextInput::make('included_products')->default('{}')->readOnly(),
            TextInput::make('excluded_products')->default('{}')->readOnly(),
            TextInput::make('included_categories')->default('{}')->readOnly(),
            TextInput::make('excluded_categories')->default('{}')->readOnly(),
            TextInput::make('included_customers')->default('{}')->readOnly(),
        ];
    }

    public static function table(Table $table): Table
    {
        // TODO: Fix the filtering and searching
        // TODO: Fix how the categories are loaded in the table.   //
        return $table
            ->columns([
                TextColumn::make('coupon_code')
                    ->searchable()
                    ->label(__('sparkcommerce::sparkcommerce.resource.coupon.creation_form.name')),
                TextColumn::make('coupon_type')
                    ->label(__('sparkcommerce::sparkcommerce.resource.coupon.creation_form.coupon_type')),
                TextColumn::make('coupon_amount'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
