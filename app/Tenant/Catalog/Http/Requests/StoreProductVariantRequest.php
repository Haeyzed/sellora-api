<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Models\AttributeValue;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adding a variant to a product with options, which needs the catalog.manage permission.
 */
final class StoreProductVariantRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesVariantDetails;

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
             * One value ID for each of the product's options, such as the IDs of "Blue" and "XL".
             *
             * @var list<string>
             */
            'values' => ['required', 'list'],
            'values.*' => ['string', 'distinct', Rule::exists(AttributeValue::class, 'public_id')],
            /** The price in minor units of the store's base currency, such as 1999 for 19.99. Can be left out until it is known; an unpriced variant can't be bought. */
            'price' => $this->priceRules(),
            /** The "was" price shown crossed out, in minor units; must be higher than the price. */
            'compare_at_price' => $this->compareAtPriceRules(),
            /** Your own stock code, unique among variants outside the trash. */
            'sku' => $this->skuRules(current: null),
            /** Such as a GTIN or EAN printed on the item. */
            'barcode' => $this->barcodeRules(),
            /** False for things that never ship, such as a service. Defaults to true. */
            'requires_shipping' => $this->requiresShippingRules(),
            /** In grams; required for anything that ships. */
            'weight_grams' => $this->weightRules(),
            /** In millimetres, all three or none. */
            'dimensions' => $this->dimensionsRules(),
            'dimensions.length_mm' => $this->dimensionRules('dimensions'),
            'dimensions.width_mm' => $this->dimensionRules('dimensions'),
            'dimensions.height_mm' => $this->dimensionRules('dimensions'),
            /** The ID of one of the product's images to show for this variant. */
            'image' => $this->imageRules($this->route('product') instanceof Product ? $this->route('product') : null),
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateVariantAsAWhole($validator, '', current: null);
            },
        ];
    }

    /**
     * The chosen values, in the order sent.
     *
     * @return list<AttributeValue>
     */
    public function chosenValues(): array
    {
        /** @var list<string> $publicIds */
        $publicIds = $this->validated('values');

        return array_values(AttributeValue::query()->whereIn('public_id', $publicIds)->get()
            ->sortBy(static fn (AttributeValue $value): int|false => array_search($value->public_id, $publicIds, true))
            ->all());
    }

    public function details(): ProductVariantData
    {
        return $this->variantData('');
    }
}
