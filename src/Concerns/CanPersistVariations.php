<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Rahat1994\SparkCommerce\Models\SCProductVariation;

trait CanPersistVariations
{
    /**
     * The variation repeater state captured from the form, or null when the
     * variations repeater was not part of the submitted form state.
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $product_variations = null;

    /**
     * Pull the variation repeater state out of the form data so it is not
     * mass assigned onto the product itself.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractVariations(array $data): array
    {
        if (array_key_exists('product_variations', $data)) {
            $this->product_variations = array_values($data['product_variations'] ?? []);

            unset($data['product_variations']);
        }

        return $data;
    }

    /**
     * Write the captured variation rows to the variations table through the
     * product's variations() relation. Does nothing when the repeater was
     * absent from the submitted form, so existing rows are kept.
     */
    protected function persistVariations(): void
    {
        if ($this->product_variations === null) {
            return;
        }

        $this->record->variations()->delete();

        foreach ($this->product_variations as $variation) {
            $this->record->variations()->create(
                $this->mapVariationFormState($variation)
            );
        }
    }

    /**
     * Map a single variation repeater item to variation table columns.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function mapVariationFormState(array $state): array
    {
        $options = $state['variation_options'] ?? [];

        return [
            'sku' => $state['sku'] ?? null,
            'variation_title' => $state['title'] ?? null,
            'enabled' => in_array('enabled', $options, true),
            'downloadable' => in_array('downloadable', $options, true),
            'virtual' => in_array('virtual', $options, true),
            'regular_price' => $state['regular_price'] ?? 0,
            'sale_price' => $state['sale_price'] ?? 0,
            'description' => $state['description'] ?? null,
            'weight' => $state['weight'] ?? null,
            'height' => $state['height'] ?? null,
            'width' => $state['width'] ?? null,
            'length' => $state['length'] ?? null,
        ];
    }

    /**
     * Load the persisted variation rows back into the repeater state shape
     * when the edit form is filled.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillVariations(array $data): array
    {
        $variations = $this->getRecord()->variations()->get();

        if ($variations->isEmpty()) {
            return $data;
        }

        // The repeater is only rendered once a generation mode is chosen.
        $data['generate_varaitions'] ??= 'generate_variations_from_attributes';

        $data['product_variations'] = $variations
            ->map(fn (SCProductVariation $variation): array => $this->mapVariationToFormState($variation))
            ->all();

        return $data;
    }

    /**
     * Map a variation row to the repeater item state shape.
     *
     * @return array<string, mixed>
     */
    protected function mapVariationToFormState(SCProductVariation $variation): array
    {
        return [
            'title' => $variation->variation_title,
            'sku' => $variation->sku,
            'variation_options' => array_values(array_filter([
                $variation->enabled ? 'enabled' : null,
                $variation->downloadable ? 'downloadable' : null,
                $variation->virtual ? 'virtual' : null,
            ])),
            'regular_price' => $variation->regular_price,
            'sale_price' => $variation->sale_price,
            'description' => $variation->description,
            'weight' => $variation->weight,
            'height' => $variation->height,
            'width' => $variation->width,
            'length' => $variation->length,
        ];
    }
}
