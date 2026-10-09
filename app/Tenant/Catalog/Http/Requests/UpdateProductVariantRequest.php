<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Changing a variant's price, codes or shipping details, which needs the catalog.manage permission. Send only what changes.
 */
final class UpdateProductVariantRequest extends FormRequest
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
            /** The price in minor units of the store's base currency, such as 1999 for 19.99. Once set, it can be changed but not removed. */
            'price' => $this->priceRules(),
            /** The "was" price shown crossed out, in minor units; must be higher than the price. Null removes it. */
            'compare_at_price' => $this->compareAtPriceRules(),
            /** Your own stock code, unique among variants outside the trash. Null removes it. */
            'sku' => $this->skuRules($this->variant()),
            /** Such as a GTIN or EAN printed on the item. Null removes it. */
            'barcode' => $this->barcodeRules(),
            /** False for things that never ship, such as a service. */
            'requires_shipping' => $this->requiresShippingRules(),
            /** In grams; required for anything that ships. */
            'weight_grams' => $this->weightRules(),
            /** In millimetres, all three or none; null removes them. */
            'dimensions' => $this->dimensionsRules(),
            'dimensions.length_mm' => $this->dimensionRules('dimensions'),
            'dimensions.width_mm' => $this->dimensionRules('dimensions'),
            'dimensions.height_mm' => $this->dimensionRules('dimensions'),
            /** The ID of one of the product's images to show for this variant; null shows none of its own. */
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
                $this->validateVariantAsAWhole($validator, '', $this->variant());
            },
        ];
    }

    public function changes(): ProductVariantData
    {
        return $this->variantData('');
    }

    private function variant(): ProductVariant
    {
        $variant = $this->route('variant');

        return $variant instanceof ProductVariant ? $variant : new ProductVariant;
    }
}
