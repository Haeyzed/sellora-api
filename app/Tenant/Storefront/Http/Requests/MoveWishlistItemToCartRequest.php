<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * Putting an item from the signed-in customer's own wishlist in their cart, in whole units.
 */
final class MoveWishlistItemToCartRequest extends FormRequest
{
    use FindsOwnWishlistItem;
    use FindsWishlistEntries;

    private ?ProductVariant $chosenVariant = null;

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
            /** The variant to buy: required for an item saved without one, and must be the saved one otherwise. */
            'variant_id' => ['nullable', 'string', 'ulid'],
            /** How many, in whole units. */
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config()->integer('cart.max_line_quantity')],
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

                $item = $this->wishlistItem();
                $variantId = $this->input('variant_id');

                if ($item->variant !== null) {
                    if (is_string($variantId) && $variantId !== $item->variant->public_id) {
                        $validator->errors()->add('variant_id', __('validation.in', ['attribute' => 'variant_id']));

                        return;
                    }

                    $this->chosenVariant = $item->variant;

                    return;
                }

                $this->chosenVariant = is_string($variantId) ? $this->buyableVariantOf($item->product, $variantId) : null;

                if ($this->chosenVariant === null) {
                    $validator->errors()->add('variant_id', is_string($variantId)
                        ? __('validation.exists', ['attribute' => 'variant_id'])
                        : __('validation.required', ['attribute' => 'variant_id']));
                }
            },
        ];
    }

    public function chosenVariant(): ProductVariant
    {
        return $this->chosenVariant ?? throw new LogicException('The variant is chosen while the request is validated.');
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }
}
