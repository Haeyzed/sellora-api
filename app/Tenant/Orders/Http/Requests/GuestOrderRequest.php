<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Tenant\Orders\Concerns\ActsAsGuestOrderHolder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A guest looking at or cancelling their order with its access token (X-Order-Token header). Without it the order is not found (404), never forbidden.
 */
final class GuestOrderRequest extends FormRequest
{
    use ActsAsGuestOrderHolder;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
