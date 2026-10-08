<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\CatalogSlug;
use App\Tenant\Catalog\Data\ProductData;
use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adding a product with its first variant, which needs the catalog.manage permission.
 */
final class StoreProductRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesProductPlacement;
    use ValidatesStoreTranslations;
    use ValidatesVariantDetails;

    public function authorize(): bool
    {
        return $this->actor()->can('create', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * By language, such as {"en": "Linen shirt"}; must include the store's default language. Up to 200 characters each.
             *
             * @var array<string, string>
             */
            'name' => ['required', 'array', $this->storeTranslatedText(200, requiresDefault: true)],
            /**
             * By language, up to 20,000 characters each.
             *
             * @var array<string, string>|null
             */
            'description' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(20000)],
            /** Lower-case letters, numbers and single hyphens, such as "linen-shirt". Made from the name in the default language when left out. */
            'slug' => ['sometimes', 'string', 'max:'.CatalogSlug::MAX_LENGTH, 'regex:'.CatalogSlug::PATTERN, Rule::unique(Product::class, 'slug')->whereNull('deleted_at')],
            ...$this->placementRules(),
            /** The first variant: what is bought, with its price and shipping details. */
            'variant' => ['required', 'array'],
            /** The price in minor units of the store's base currency, such as 1999 for 19.99. Can be left out until it is known; an unpriced variant can't be bought. */
            'variant.price' => $this->priceRules(),
            /** The "was" price shown crossed out, in minor units; must be higher than the price. */
            'variant.compare_at_price' => $this->compareAtPriceRules(),
            /** Your own stock code, unique among variants outside the trash. */
            'variant.sku' => $this->skuRules(current: null),
            /** Such as a GTIN or EAN printed on the item. */
            'variant.barcode' => $this->barcodeRules(),
            /** False for things that never ship, such as a service. Defaults to true. */
            'variant.requires_shipping' => $this->requiresShippingRules(),
            /** In grams; required for anything that ships. */
            'variant.weight_grams' => $this->weightRules(),
            /** In millimetres, all three or none. */
            'variant.dimensions' => $this->dimensionsRules(),
            'variant.dimensions.length_mm' => $this->dimensionRules('variant.dimensions'),
            'variant.dimensions.width_mm' => $this->dimensionRules('variant.dimensions'),
            'variant.dimensions.height_mm' => $this->dimensionRules('variant.dimensions'),
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validatePrimaryCategory($validator, currentCategoryIds: []);
                $this->validateVariantAsAWhole($validator, 'variant.', current: null);
            },
        ];
    }

    public function productData(): ProductData
    {
        /** @var array<string, string|null> $name */
        $name = $this->validated('name');
        /** @var array<string, string|null>|null $description */
        $description = $this->validated('description');
        $slug = $this->validated('slug');
        $brand = $this->validated('brand');

        return new ProductData(
            name: $name,
            description: $description,
            slug: is_string($slug) ? $slug : null,
            brand: is_string($brand) ? Brand::query()->where('public_id', $brand)->firstOrFail() : null,
            categories: $this->chosenCategories() ?? [],
            primaryCategory: $this->chosenPrimaryCategory(),
        );
    }

    public function variantDetails(): ProductVariantData
    {
        return $this->variantData('variant.');
    }
}
