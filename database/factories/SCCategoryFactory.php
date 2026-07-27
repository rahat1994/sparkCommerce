<?php

namespace Rahat1994\SparkCommerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rahat1994\SparkCommerce\Models\SCCategory;

class SCCategoryFactory extends Factory
{
    protected $model = SCCategory::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'parent_id' => null,
            'user_id' => null,
        ];
    }

    public function childOf(SCCategory $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }
}
