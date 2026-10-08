<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The rules for where a product is shown: its brand, its categories and the one it is mainly listed under.
 *
 * Only brands and categories outside the trash can be chosen.
 *
 * @mixin FormRequest
 */
trait ValidatesProductPlacement
{
    /** @var list<Category>|null */
    private ?array $chosenCategories = null;

    /**
     * @return array<string, mixed>
     */
    protected function placementRules(): array
    {
        return [
            /** The brand's ID, outside the trash. Null leaves the product without a brand. */
            'brand' => ['sometimes', 'nullable', 'string', Rule::exists(Brand::class, 'public_id')->whereNull('deleted_at')],
            /**
             * Every category the product is in, by ID, outside the trash; replaces the current ones. An empty list removes them all.
             *
             * @var list<string>
             */
            'categories' => ['sometimes', 'array', 'list', 'max:20'],
            'categories.*' => ['required', 'string', 'distinct', Rule::exists(Category::class, 'public_id')->whereNull('deleted_at')],
            /** The ID of the category it is mainly listed under: one of its categories. Left out, the current one stays if it can, else the first. */
            'primary_category' => ['sometimes', 'string'],
        ];
    }

    /**
     * The primary category must be one of the categories sent, or of the current ones when none are sent.
     *
     * @param  list<int>  $currentCategoryIds
     */
    protected function validatePrimaryCategory(Validator $validator, array $currentCategoryIds): void
    {
        if (! $this->filled('primary_category') || $validator->errors()->hasAny(['categories', 'categories.*', 'primary_category'])) {
            return;
        }

        $primaryCategory = $this->chosenPrimaryCategory();
        $categoryIds = $this->has('categories')
            ? array_map(static fn (Category $category): int => $category->id, $this->chosenCategories() ?? [])
            : $currentCategoryIds;

        if ($primaryCategory === null || ! in_array($primaryCategory->id, $categoryIds, true)) {
            $validator->errors()->add('primary_category', __('validation.custom.primary_category.not_in_categories'));
        }
    }

    /**
     * The categories sent, in the order sent; null when none were sent.
     *
     * @return list<Category>|null
     */
    protected function chosenCategories(): ?array
    {
        if (! $this->has('categories')) {
            return null;
        }

        if ($this->chosenCategories !== null) {
            return $this->chosenCategories;
        }

        /** @var list<string> $publicIds */
        $publicIds = $this->array('categories');
        $categories = Category::query()->whereIn('public_id', $publicIds)->get()->keyBy('public_id');

        return $this->chosenCategories = array_values(array_filter(array_map(
            static fn (string $publicId): ?Category => $categories->get($publicId),
            $publicIds,
        )));
    }

    protected function chosenPrimaryCategory(): ?Category
    {
        if (! $this->filled('primary_category')) {
            return null;
        }

        return Category::withTrashed()->where('public_id', $this->string('primary_category')->value())->first();
    }
}
