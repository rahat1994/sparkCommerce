<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Facades\Filament;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Rahat1994\SparkCommerce\Concerns\HasAttributes;
use Rahat1994\SparkCommerce\Concerns\HasDimension;
use Rahat1994\SparkCommerce\Concerns\HasInventory;
use Rahat1994\SparkCommerce\Concerns\HasPrice;
use Rahat1994\SparkCommerce\Concerns\HasVariation;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\CreateProduct;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\EditProduct;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\ListProducts;
use Rahat1994\SparkCommerce\Forms\Components\CategoriesField;
use Rahat1994\SparkCommerce\Models\SCCategory;
use Rahat1994\SparkCommerce\Models\SCProduct;

class ProductResource extends Resource
{
    use HasAttributes;
    use HasDimension;
    use HasInventory;
    use HasPrice;
    use HasVariation;

    protected static ?string $model = SCProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('sku'),
                TextColumn::make('regular_price'),
            ])
            ->filters([
                //
            ])
            ->searchable(true)
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make([
                    TextInput::make('name')
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
                    Section::make('Publish')->schema([
                        Placeholder::make('Status'),
                        Placeholder::make('Visibility'),
                        Placeholder::make('Publish immediately'),
                    ])->grow(false),
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
        if (class_exists('SparkcommerceMultivendor') && $vendorId = Filament::getTenant()) {
            return SCCategory::where('vendor_id', $vendorId->id)->get()->toArray();
        }

        return SCCategory::all()->toArray();
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
                self::getLinkedProductsTab(),
                self::getAttributesTab(),
                self::getVariationsTab(),
                self::getAdvancedTab(),
                self::getMoreOptionsTab(),
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

    public static function getMoreOptionsTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.more_option'))
            ->schema([
                Placeholder::make('Info')
                    ->content(new HtmlString('<p>Coming Soon</p>')),
            ]);
    }

    public static function getAdvancedTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.advanced'))
            ->schema([
                Placeholder::make('Info')
                    ->content(new HtmlString('<p>Coming Soon</p>')),
            ]);
    }

    public static function getPricingTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.pricing'))
            ->schema([
                Placeholder::make('Info')
                    ->content(new HtmlString('<p>Coming Soon</p>')),
            ]);
    }

    public static function getLinkedProductsTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.linked_products'))
            ->schema([
                Placeholder::make('Info')
                    ->content(new HtmlString('<p>Coming Soon</p>')),
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
