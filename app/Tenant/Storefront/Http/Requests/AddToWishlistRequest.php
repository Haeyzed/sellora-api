<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Storefront\Data\WishlistEntryData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * Saving a product, or one of its variants, to the signed-in customer's own wishlist.
 */
final class AddToWishlistRequest extends FormRequest
{
    use ActsAsCustomer;
    use FindsWishlistEntries;

    private ?WishlistEntryData $entry = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** A product customers can buy. Out of stock is fine. */
            'product_id' => ['required', 'string', 'ulid'],
            /** One of the product's variants, when the customer chose one; null or left out to save the product before choosing. */
            'variant_id' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $product = $this->buyableProduct($this->string('product_id')->value());

                if ($product === null) {
                    $validator->errors()->add('product_id', __('validation.exists', ['attribute' => 'product_id']));

                    return;
                }

                $variantId = $this->input('variant_id');
                $variant = is_string($variantId) ? $this->buyableVariantOf($product, $variantId) : null;

                if (is_string($variantId) && $variant === null) {
                    $validator->errors()->add('variant_id', __('validation.exists', ['attribute' => 'variant_id']));

                    return;
                }

                $this->entry = new WishlistEntryData($product, $variant);
            },
        ];
    }

    public function entry(): WishlistEntryData
    {
        return $this->entry ?? throw new LogicException('The entry is found while the request is validated.');
    }
}
