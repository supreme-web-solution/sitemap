<?php

namespace Database\Factories;

use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => null,
            'price_cents' => 0,
            'is_default' => false,
            'is_active' => true,
            'limits' => [],
            'features' => [],
            'sort_order' => 0,
        ];
    }

    /**
     * Make this the plan teams start on.
     */
    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /**
     * Include every feature.
     */
    public function withAllFeatures(): static
    {
        return $this->state(fn () => ['features' => array_map(fn (PlanFeature $feature) => $feature->value, PlanFeature::cases())]);
    }

    /**
     * Cap the given metrics, e.g. `['lead_searches' => 2]`.
     *
     * @param  array<string, int|null>  $limits
     */
    public function limited(array $limits): static
    {
        return $this->state(fn () => ['limits' => $limits]);
    }
}