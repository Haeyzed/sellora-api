<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\CatalogSlug;
use App\Tenant\Catalog\Data\ProductData;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Changing a product, which needs the catalog.manage permission. Send only what changes.
 */
final class UpdateProductRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesProductPlacement;
    use ValidatesStoreTranslations;

    public function authorize(): bool
    {
        return $this->actor()->can('update', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * By language. Only the languages sent change; null removes a language, except the default.
             *
             * @var array<string, string|null>
             */
            'name' => ['sometimes', 'array', $this->storeTranslatedText(200)],
            /**
             * By language. Only the languages sent change; null for a language removes it, and null for the whole description removes it in every language.
             *
             * @var array<string, string|null>|null
             */
            'description' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(20000)],
            /** Lower-case letters, numbers and single hyphens. Renaming the product doesn't change it. */
            'slug' => [
                'sometimes', 'string', 'max:'.CatalogSlug::MAX_LENGTH, 'regex:'.CatalogSlug::PATTERN,
                Rule::unique(Product::class, 'slug')->whereNull('deleted_at')->ignore($this->product()->getKey()),
            ],
            ...$this->placementRules(),
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validatePrimaryCategory($validator, $this->currentCategoryIds());
            },
        ];
    }

    public function changes(): ProductData
    {
        /** @var array<string, string|null>|null $name */
        $name = $this->validated('name');
        /** @var array<string, string|null>|null $description */
        $description = $this->validated('description');
        $slug = $this->validated('slug');
        $brand = $this->validated('brand');
        $primaryCategory = $this->chosenPrimaryCategory();
        $categories = $this->chosenCategories();

        // Choosing only a new primary category keeps the current categories.
        if ($categories === null && $primaryCategory !== null) {
            $categories = array_values($this->product()->categories()->get()->all());
        }

        return new ProductData(
            name: $name,
            description: $description,
            removesDescription: $this->has('description') && $description === null,
            slug: is_string($slug) ? $slug : null,
            brand: is_string($brand) ? Brand::query()->where('public_id', $brand)->firstOrFail() : null,
            removesBrand: $this->has('brand') && $brand === null,
            categories: $categories,
            primaryCategory: $primaryCategory,
        );
    }

    private function product(): Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : new Product;
    }

    /**
     * @return list<int>
     */
    private function currentCategoryIds(): array
    {
        return array_values($this->product()->categories()->get()->map(static fn (Category $category): int => $category->id)->all());
    }
}
