<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * How many of a line's variant the shopper now wants. Open to guests (with their cart token) and signed-in customers.
 */
final class ChangeCartLineRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The new quantity, replacing the old one. To take the line out, delete it. */
            'quantity' => ['required', 'integer:strict', 'min:1', 'max:'.config()->integer('cart.max_line_quantity')],
        ];
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }
}
