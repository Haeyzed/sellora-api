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
 * Changing or moving a category, which needs the catalog.manage permission. Send only what changes.
 */
final class UpdateCategoryRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesStoreTranslations;

    public function authorize(): bool
    {
        return $this->actor()->can('update', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            /**
             * By language. Only the languages sent change; null removes a language, except the default.
             *
             * @var array<string, string|null>
             */
            'name' => ['sometimes', 'array', $this->storeTranslatedText(120)],
            /**
             * By language. Only the languages sent change; null for a language removes it, and null for the whole description removes it in every language.
             *
             * @var array<string, string|null>|null
             */
            'description' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(5000)],
            /** Lower-case letters, numbers and single hyphens. Renaming the category doesn't change it. */
            'slug' => [
                'sometimes', 'string', 'max:'.CatalogSlug::MAX_LENGTH, 'regex:'.CatalogSlug::PATTERN,
                Rule::unique(Category::class, 'slug')->whereNull('deleted_at')->ignore($category instanceof Category ? $category->getKey() : null),
            ],
            /** The ID of the category to move under, with the whole branch; null moves it to the top level. A moved category goes last among its new siblings. */
            'parent' => ['sometimes', 'nullable', 'string', Rule::exists(Category::class, 'public_id')->whereNull('deleted_at')],
        ];
    }

    public function changes(): CategoryData
    {
        /** @var array<string, string|null>|null $name */
        $name = $this->validated('name');
        /** @var array<string, string|null>|null $description */
        $description = $this->validated('description');
        $slug = $this->validated('slug');
        $parent = $this->validated('parent');

        return new CategoryData(
            name: $name,
            description: $description,
            removesDescription: $this->has('description') && $description === null,
            slug: is_string($slug) ? $slug : null,
            parent: is_string($parent) ? Category::query()->where('public_id', $parent)->firstOrFail() : null,
            movesToTopLevel: $this->has('parent') && $parent === null,
        );
    }
}
