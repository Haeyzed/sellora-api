<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Requests;

use App\Tenant\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Some of a variant a shopper wants to put in their cart. Open to guests (with their cart token) and signed-in customers.
 */
final class AddCartLineRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The variant's ID. */
            'variant' => ['required', 'string', 'size:26', Rule::exists(ProductVariant::class, 'public_id')],
            /** How many to add to what is already in the cart; 1 when left out. */
            'quantity' => ['sometimes', 'integer:strict', 'min:1', 'max:'.config()->integer('cart.max_line_quantity')],
        ];
    }

    public function variant(): ProductVariant
    {
        return ProductVariant::withTrashed()->with('product')->where('public_id', $this->string('variant')->value())->sole();
    }

    public function quantity(): int
    {
        return $this->has('quantity') ? $this->integer('quantity') : 1;
    }
}
