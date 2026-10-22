<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Placing the order: the checkout, the quote version the shopper confirmed, and where a gateway sends them back.
 */
final class PlaceCheckoutOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The checkout's ID. */
            'checkout' => ['required', 'string', 'max:26'],
            /** The quote version the shopper confirmed. */
            'version' => ['required', 'integer', 'min:1'],
            /** The storefront page a gateway sends the buyer back to; needed when paying on a gateway's page. Arriving there proves nothing: ask for the payment's status. */
            'return_url' => ['nullable', 'string', 'url:https,http', 'max:2048'],
        ];
    }
}
