<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Plans\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
final class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, asText: true),
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A plan that includes the given modules and integrations.
     */
    public function withFeatures(string ...$featureKeys): self
    {
        return $this->afterCreating(static function (Plan $plan) use ($featureKeys): void {
            foreach ($featureKeys as $featureKey) {
                $plan->features()->create(['feature_key' => $featureKey]);
            }
        });
    }

    /**
     * A plan with the given usage limits (null means unlimited).
     *
     * @param  array<string, int|null>  $limits
     */
    public function withLimits(array $limits): self
    {
        return $this->afterCreating(static function (Plan $plan) use ($limits): void {
            foreach ($limits as $limitKey => $limitValue) {
                $plan->limits()->create(['limit_key' => $limitKey, 'limit_value' => $limitValue]);
            }
        });
    }
}
