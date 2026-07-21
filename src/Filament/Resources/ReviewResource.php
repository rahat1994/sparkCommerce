<?php

namespace Rahat1994\SparkCommerce\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Rahat1994\SparkCommerce\Filament\Resources\ReviewResource\Pages\CreateReview;
use Rahat1994\SparkCommerce\Filament\Resources\ReviewResource\Pages\EditReview;
use Rahat1994\SparkCommerce\Filament\Resources\ReviewResource\Pages\ListReviews;
use Rahat1994\SparkCommerce\Models\SCReview;

class ReviewResource extends Resource
{
    protected static ?string $model = SCReview::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-star';

    public static function getModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.review.model_label');
        // return __('filament-user-activity::user-activity.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.review.model_plural_label');
        // return __('filament-user-activity::user-activity.resource.model_plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('sparkcommerce::sparkcommerce.resource.review.navigation_group');
        // return __('filament-user-activity::user-activity.resource.navigation');
    }

    public static function getNavigationLabel(): string
    {
        return __('sparkcommerce::sparkcommerce.resource.review.navigation');
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
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListReviews::route('/'),
            'create' => CreateReview::route('/create'),
            'edit' => EditReview::route('/{record}/edit'),
        ];
    }
}
