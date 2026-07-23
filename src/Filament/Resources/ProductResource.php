<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Rahat1994\SparkCommerce\Concerns\CanInteractWithTenant;
use Rahat1994\SparkCommerce\Concerns\HasAttributes;
use Rahat1994\SparkCommerce\Concerns\HasDimension;
use Rahat1994\SparkCommerce\Concerns\HasInventory;
use Rahat1994\SparkCommerce\Concerns\HasPrice;
use Rahat1994\SparkCommerce\Concerns\HasVariation;
use Rahat1994\SparkCommerce\Filament\Concerns\HasSparkCommercePanelAccess;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\CreateProduct;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\EditProduct;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\ListProducts;
use Rahat1994\SparkCommerce\Forms\Components\CategoriesField;
use Rahat1994\SparkCommerce\Models\SCCategory;
use Rahat1994\SparkCommerce\Models\SCProduct;

class ProductResource extends Resource
{
    use CanInteractWithTenant;
    use HasAttributes;
    use HasDimension;
    use HasInventory;
    use HasPrice;
    use HasSparkCommercePanelAccess;
    use HasVariation;

    protected static ?string $model = SCProduct::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-gift';

    public static function table(Table $table): Table
    {
        $currency = self::getTenantCurrency();

        if (static::hasMacro('getAdditionalActions')) {
            $actions = static::getAdditionalActions();
        } else {
            $actions = [
                EditAction::make(),
                DeleteAction::make(),
            ];
        }

        return $table
            ->columns([
                TextColumn::make('name')
                    ->description(fn (SCProduct $record): string => substr(strip_tags($record->description), 0, 100))
                    ->wrap(),

                TextColumn::make('product_type')
                    ->badge()->color(fn (string $state): string => match ($state) {
                        'simple' => 'success',
                        'variable' => 'yellow',
                        'digital' => 'blue',
                        'service' => 'purple',
                    }),
                TextColumn::make('sku'),
                TextColumn::make('regular_price')->money($currency),
            ])
            ->filters([
                //
            ])
            ->searchable(true)
            ->recordActions($actions)
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    TextInput::make('name')
                        ->required()
                        ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_name')),
                    RichEditor::make('description')
                        ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.description')),
                    Select::make('product_type')->options([
                        'simple' => 'Simple',
                        'variable' => 'Variable',
                    ])->default('simple')->label('Product Type')->live(),
                    self::getCategoriesSection(),
                    self::getProductDataSection(),
                ])->columnSpan(3),
                Group::make([
                    Section::make('Product Image')->schema([
                        SpatieMediaLibraryFileUpload::make('product_image')
                            ->collection('product_image')
                            ->hiddenLabel()
                            ->image(),
                    ])->grow(false),
                    Section::make('Product gallery')->schema([
                        SpatieMediaLibraryFileUpload::make('product_image_gallery')
                            ->collection('product_image_gallery')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->hiddenLabel(),
                    ])->grow(false),

                    Section::make('Product tags')->schema([
                        SpatieTagsInput::make('product_tags')
                            ->label('Product Tags'),
                    ])->grow(false),
                ]),
            ])->columns(4);
    }

    public static function getShopCategories()
    {
        if (static::isMultivendorInstalled() && $tenant = Filament::getTenant()) {
            return SCCategory::where('vendor_id', $tenant->id)->get()->toArray();
        }

        return SCCategory::all()->toArray();
    }

    /**
     * Detect the multivendor package through config instead of a fragile
     * facade-alias check: the configured vendor model must exist.
     */
    protected static function isMultivendorInstalled(): bool
    {
        $vendorModel = config('sparkcommerce.vendor_model');

        return $vendorModel !== null && class_exists($vendorModel);
    }

    public static function getProductDimensionFields()
    {
        // TODO: Add hooks to modify the array
        return Fieldset::make('product_dimensions')
            ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.fieldset_name'))
            ->schema(self::getDimensionInputes())->columns(3);
    }

    public static function getProductDataSection(): Tabs
    {
        // TODO: Add hooks to modify the array
        return Tabs::make('product_data')
            ->tabs([
                self::getGeneralTab(),
                self::getInventoryTab(),
                self::getShippingTab(),
                self::getAttributesTab(),
                self::getVariationsTab(),
            ]);
    }

    public static function getCategoriesSection()
    {
        return Section::make('Product categories')->schema([
            CategoriesField::make('product_categories')
                ->hiddenLabel()
                ->categories(self::getShopCategories()),
        ]);
    }

    public static function getShippingTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.shipping'))
            ->schema([
                self::getProductDimensionFields(),
            ]);
    }

    public static function getGeneralTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.general'))
            ->schema(self::getPriceInputs());
    }

    public static function getInventoryTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.inventory'))
            ->schema(self::getInventoryInputs());
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
}
