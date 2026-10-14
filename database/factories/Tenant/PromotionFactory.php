<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Shared\Money\Decimal;
use App\Tenant\Promotions\Enums\PromotionAppliesTo;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Enums\PromotionType;
use App\Tenant\Promotions\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A running automatic promotion taking 10% off the order, unless told otherwise.
 *
 * @extends Factory<Promotion>
 */
final class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'title' => ['en' => '10% off'],
            'method' => PromotionMethod::Automatic,
            'type' => PromotionType::Percentage,
            'applies_to' => PromotionAppliesTo::Order,
            'percent' => Decimal::of(10),
            'starts_at' => now()->subDay(),
            'is_active' => true,
        ];
    }

    public function withCode(): self
    {
        return $this->state(['method' => PromotionMethod::Code]);
    }
}
