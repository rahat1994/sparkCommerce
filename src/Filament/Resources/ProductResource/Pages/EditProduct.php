<?php

namespace Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;

class EditProduct extends EditRecord
{
    public static string $resource = ProductResource::class;
    protected array $product_categories = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // dd($this->record->id);
        // $data['slug'] = Str::slug($data['name']);
        $data['user_id'] = auth()->user()->id;
        $data['price'] = $data['regular_price'];
        dd($data['product_categories']);
        if (isset($data['product_categories'])) {
            $this->product_categories = $data['product_categories'];
            // unset($data['product_categories']);
        }

        return $data;
    }
}
