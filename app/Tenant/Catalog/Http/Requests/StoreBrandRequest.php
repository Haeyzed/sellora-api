<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\CatalogSlug;
use App\Tenant\Catalog\Data\BrandData;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a brand, which needs the catalog.manage permission.
 */
final class StoreBrandRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesStoreTranslations;

    public function authorize(): bool
    {
        return $this->actor()->can('create', Brand::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * By language, such as {"en": "Adidas", "fr": "Adidas"}; must include the store's default language. Up to 120 characters each.
             *
             * @var array<string, string>
             */
            'name' => ['required', 'array', $this->storeTranslatedText(120, requiresDefault: true)],
            /**
             * By language, up to 2,000 characters each.
             *
             * @var array<string, string>|null
             */
            'description' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(2000)],
            /** Lower-case letters, numbers and single hyphens, such as "adidas". Made from the name in the default language when left out. */
            'slug' => ['sometimes', 'string', 'max:'.CatalogSlug::MAX_LENGTH, 'regex:'.CatalogSlug::PATTERN, Rule::unique(Brand::class, 'slug')->whereNull('deleted_at')],
        ];
    }

    public function brandData(): BrandData
    {
        /** @var array<string, string|null> $name */
        $name = $this->validated('name');
        /** @var array<string, string|null>|null $description */
        $description = $this->validated('description');
        $slug = $this->validated('slug');

        return new BrandData(name: $name, description: $description, slug: is_string($slug) ? $slug : null);
    }
}
