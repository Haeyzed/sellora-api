<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Data\BrandData;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changing a brand, which needs the catalog.manage permission. Send only what changes.
 */
final class UpdateBrandRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesStoreTranslations;

    public function authorize(): bool
    {
        return $this->actor()->can('update', Brand::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $brand = $this->route('brand');

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
            'description' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(2000)],
            /** Lower-case letters, numbers and single hyphens. Renaming the brand doesn't change it. */
            'slug' => [
                'sometimes', 'string', 'max:'.Brand::SLUG_MAX_LENGTH, 'regex:'.StoreBrandRequest::SLUG_PATTERN,
                Rule::unique(Brand::class, 'slug')->whereNull('deleted_at')->ignore($brand instanceof Brand ? $brand->getKey() : null),
            ],
        ];
    }

    public function changes(): BrandData
    {
        /** @var array<string, string|null>|null $name */
        $name = $this->validated('name');
        /** @var array<string, string|null>|null $description */
        $description = $this->validated('description');
        $slug = $this->validated('slug');

        return new BrandData(
            name: $name,
            description: $description,
            removesDescription: $this->has('description') && $description === null,
            slug: is_string($slug) ? $slug : null,
        );
    }
}
