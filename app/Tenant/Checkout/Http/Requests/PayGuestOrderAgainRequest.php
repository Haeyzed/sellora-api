<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Requests;

use App\Tenant\Orders\Concerns\ActsAsGuestOrderHolder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A guest paying again for their order with its access token (X-Order-Token header): how, and where a gateway sends them back.
 */
final class PayGuestOrderAgainRequest extends FormRequest
{
    use ActsAsGuestOrderHolder;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** "gateway:{key}" for a connected gateway, or "bank_transfer" or "cash_on_delivery" while the store takes them. */
            'payment_method' => ['required', 'string', 'max:96'],
            /** The storefront page a gateway sends the buyer back to; needed for a gateway. */
            'return_url' => ['nullable', 'string', 'url:https,http', 'max:2048'],
        ];
    }
}
