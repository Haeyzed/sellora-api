<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Tenant\Orders\Concerns\ActsAsGuestOrderHolder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A guest following the store's own deliveries of their order, with its access token in the X-Order-Token header (never the URL). Without it the order is not found (404).
 */
final class GuestDeliveryTrackingRequest extends FormRequest
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
