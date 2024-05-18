<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\CreateProduct;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\EditProduct;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\ListProducts;
use Rahat1994\SparkCommerce\Forms\Components\CategoriesField;
use Rahat1994\SparkCommerce\Models\SCCategory;
use Rahat1994\SparkCommerce\Models\SCProduct;

class ProductResource extends Resource
{
    protected static ?string $model = SCProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

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

                Group::make([
                    TextInput::make('name')
                        ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_name')),
                    RichEditor::make('description')
                        ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.description')),
                    Select::make('product_type')->options([
                        'simple' => 'Simple',
                        'variable' => 'Variable',
                    ])->default('simple')->label('Product Type')->live(),
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
                            ->hiddenLabel()
                            ->image(),
                    ])->grow(false),
                    Section::make('Product gallery')->schema([
                        SpatieMediaLibraryFileUpload::make('product_image_gallery')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->hiddenLabel(),
                    ])->grow(false),
                    Section::make('Product categories')->schema([
                        CategoriesField::make('product_categories')
                            ->hiddenLabel()
                            ->categories(SCCategory::all()->toArray()),
                    ]),
                    Section::make('Product tags')->schema([
                        SpatieTagsInput::make('product_tags')
                            ->label('Product Tags'),
                    ])->grow(false),
                ]),
            ])->columns(4);
    }

    public static function getProductDimensionFields()
    {
        return Fieldset::make('product_dimensions')
            ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.fieldset_name'))
            ->schema([
                TextInput::make('weight')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.weight'))->columnSpan(3),
                TextInput::make('height')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.height')),
                TextInput::make('width')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.width')),
                TextInput::make('length')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.length')),
            ])->columns(3);
    }

    public static function getProductDataSection(): Tabs
    {
        return Tabs::make('product_data')
            ->tabs([
                self::getInventoryTab(),
                self::getShippingTab(),
                self::getLinkedProductsTab(),
                self::getAttributesTab(),
                self::getVariationsTab(),
                self::getAdvancedTab(),
                self::getMoreOptionsTab(),

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

    public static function getAttributesTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.attributes'))
            ->schema([
                Repeater::make('product_attributes')
                    ->label('Product Attributes')
                    ->schema([
                        Fieldset::make('product_attributes')
                            ->label('Product Attributes')
                            ->schema([
                                CheckboxList::make('visible_on_the_product_page')
                                    ->options([
                                        'visible_on_the_product_page' => 'Visible on the product page',
                                        'used_for_variations' => 'Used for varaitions',
                                    ])
                                    ->default(['visible_on_the_product_page', 'used_for_variations'])
                                    ->label('Visible on the product page')->columnSpan(2),
                                TextInput::make('attribute_name')
                                    ->label('Attribute Name'),
                                Repeater::make('attribute_values')
                                    ->label('Attribute Values')
                                    ->simple(
                                        TextInput::make('attribute_value')
                                            ->label('Attribute Value'),
                                    )->collapsible(),

                            ]),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(
                        fn (array $state): ?string => $state['attribute_name'] ?? null
                    ),
            ]);
    }

    public static function variationCombinations($arrays, $i = 0)
    {

        if (! isset($arrays[$i])) {
            return [];
        }
        if ($i == count($arrays) - 1) {
            return $arrays[$i];
        }

        // get combinations from subsequent arrays
        $tmp = self::variationCombinations(array_values($arrays), $i + 1);

        $result = [];

        // concat each array from tmp with each element from $arrays[$i]
        foreach ($arrays[$i] as $v) {
            foreach ($tmp as $t) {
                $result[] = is_array($t) ?
                    array_merge([$v], $t) :
                    [$v, $t];
            }
        }

        return $result;
    }

    public static function generateVariations($data)
    {

        $variations = array_map(function ($attribute) {
            return array_map(function ($value) use ($attribute) {
                return $attribute['attribute_name'] . ': ' . $value['attribute_value'];
            }, $attribute['attribute_values']);
        }, $data);

        $repeater_value = array_map(function ($attributes) {
            $values = implode(' ', $attributes);

            return [
                'title' => $values,
                'sku' => $values,
            ];
        }, self::variationCombinations(array_values($variations)));

        return $repeater_value;
    }

    public static function getVariationsTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.variations'))
            ->schema(function (Get $get) {

                $attributes = array_values($get('product_attributes'));

                if (count($attributes) > 0 && $attributes[0]['attribute_name'] == null) {
                    return [Placeholder::make('Info')
                        ->content(new HtmlString('<p>Please add attributes</p>'))];
                } else {
                    return array_merge([Fieldset::make('Create Variations')->schema(
                        [Select::make('generate_varaitions')
                            ->label('Create Variations')
                            ->options([
                                'generate_variations_from_attributes' => 'Generate Variations from Attributes',
                                'create_variations_manually' => 'Create Variations manually',
                            ]), Actions::make([
                                FormAction::make('Select')
                                    ->icon('heroicon-m-bars-3')
                                    ->action(function (Get $get, Set $set, $state) {
                                        $set('product_variations', self::generateVariations($get('product_attributes')));
                                    }),
                            ])->verticalAlignment(VerticalAlignment::End)]
                    )], self::getVariationsRepeaterField());
                }
            })->hidden(fn (Get $get) => $get('product_type') == 'simple');
    }

    public static function getVariationsRepeaterField()
    {

        return [
            Section::make('Variations')
                ->schema(function (Get $get) {
                    $generate_variations = $get('generate_varaitions');
                    // dd($generate_variations);
                    if ($generate_variations == null) {
                        return [];
                    } elseif ($generate_variations == 'create_variations_manually') {
                        return [Placeholder::make('Info')
                            ->content(new HtmlString('<p>Generate Manually</p>'))];
                    } else {
                        return [
                            Repeater::make('product_variations')
                                ->label('Product variations')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    Fieldset::make('variation')
                                        ->label('Variation')
                                        ->schema([
                                            Group::make([
                                                FileUpload::make('variation_image')
                                                    ->image(),
                                                TextInput::make('sku')
                                                    ->label('SKU'),
                                            ])->columnSpanFull(),
                                            CheckboxList::make('variation_options')
                                                ->options([
                                                    'enabled' => 'Enabled',
                                                    'downloadable' => 'Downloadable',
                                                    'virtual' => 'Virtual',
                                                    // 'manage_stock' => 'Manage Stock',
                                                ])
                                                ->default('enabled')
                                                ->label('Visible on the product page')->columnSpan(2),

                                            TextInput::make('regular_price'),
                                            TextInput::make('sale_price'),
                                            Select::make('stock_status')
                                                ->options([
                                                    'instock' => 'In Stock',
                                                    'outofstock' => 'Out of Stock',
                                                ])->default('instock'),

                                            self::getProductDimensionFields(),
                                            RichEditor::make('description')
                                                ->label('Description')->columnSpan(2),

                                        ]),
                                ])->itemLabel(
                                    fn (array $state): ?string => $state['title'] ?? null
                                ),
                        ];
                    }
                })->hidden(fn (Get $get) => $get('generate_varaitions') == null),
        ];
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

    public static function getInventoryTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.inventory'))
            ->schema([
                TextInput::make('sku')
                    ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.sku')),
                Placeholder::make('notice')
                    ->content(new HtmlString('<p>Needs work like woocommerce</p>')),
                Section::make('Inventory')
                    ->description('Settings for inventory')
                    ->schema([
                        TextInput::make('stock_quantity')
                            ->label('Stock Quantity')->numeric(),
                        Radio::make('should_allow_backorders')
                            ->label('Allow backorders?')
                            ->options([
                                'do_not_allow' => 'Do not allow',
                                'allow_notify_customer' => 'Allow, but notify customer',
                                'allow' => 'Allow',
                            ])->default('do_not_allow')->inline()->inlineLabel(false),
                        TextInput::make('low_stock_threshold')
                            ->label('Low stock threshold')->numeric(),
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
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
