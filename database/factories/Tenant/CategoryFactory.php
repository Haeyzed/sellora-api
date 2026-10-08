<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Tenant\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['en' => fake()->unique()->words(2, true)],
        ];
    }

    public function under(Category $parent): self
    {
        return $this->state(['parent_id' => $parent->id]);
    }
}
