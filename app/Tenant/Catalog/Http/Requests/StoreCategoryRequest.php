<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\CatalogSlug;
use App\Tenant\Catalog\Data\CategoryData;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a category, which needs the catalog.manage permission.
 */
final class StoreCategoryRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesStoreTranslations;

    public function authorize(): bool
    {
        return $this->actor()->can('create', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * By language, such as {"en": "Shirts", "fr": "Chemises"}; must include the store's default language. Up to 120 characters each.
             *
             * @var array<string, string>
             */
            'name' => ['required', 'array', $this->storeTranslatedText(120, requiresDefault: true)],
            /**
             * By language, up to 5,000 characters each.
             *
             * @var array<string, string>|null
             */
            'description' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(5000)],
            /** Lower-case letters, numbers and single hyphens, such as "mens-shirts". Made from the name in the default language when left out. */
            'slug' => ['sometimes', 'string', 'max:'.CatalogSlug::MAX_LENGTH, 'regex:'.CatalogSlug::PATTERN, Rule::unique(Category::class, 'slug')->whereNull('deleted_at')],
            /** The ID of the category to sit under, outside the trash. Left out or null, the category goes at the top level. */
            'parent' => ['sometimes', 'nullable', 'string', Rule::exists(Category::class, 'public_id')->whereNull('deleted_at')],
        ];
    }

    public function categoryData(): CategoryData
    {
        /** @var array<string, string|null> $name */
        $name = $this->validated('name');
        /** @var array<string, string|null>|null $description */
        $description = $this->validated('description');
        $slug = $this->validated('slug');
        $parent = $this->validated('parent');

        return new CategoryData(
            name: $name,
            description: $description,
            slug: is_string($slug) ? $slug : null,
            parent: is_string($parent) ? Category::query()->where('public_id', $parent)->firstOrFail() : null,
        );
    }
}
