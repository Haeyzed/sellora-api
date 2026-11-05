<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing how the store takes returns; anything left out stays as it is. Needs the returns.manage permission.
 */
final class ChangeReturnSettingsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', OrderReturn::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** How many days after a line's delivery a buyer may ask to return it; 0 means only staff can start returns. */
            'window_days' => ['sometimes', 'integer:strict', 'min:0', 'max:'.config()->integer('returns.max_window_days')],
            /** Whether customers and guests may ask for returns themselves; staff always can. */
            'customer_requests' => ['sometimes', 'boolean:strict'],
            /** Whether a buyer must add a photo when the reason claims the store was at fault (damaged, the wrong item, faulty or not as described). */
            'photos_required' => ['sometimes', 'boolean:strict'],
            /** Whether the buyer pays for an exchange's replacement delivery when the store wasn't at fault; it is always free when it was. Off by default. */
            'charge_shipping_on_exchanges' => ['sometimes', 'boolean:strict'],
        ];
    }

    public function windowDays(): ?int
    {
        return $this->has('window_days') ? $this->integer('window_days') : null;
    }

    public function customerRequests(): ?bool
    {
        return $this->has('customer_requests') ? $this->boolean('customer_requests') : null;
    }

    public function photosRequired(): ?bool
    {
        return $this->has('photos_required') ? $this->boolean('photos_required') : null;
    }

    public function chargeShippingOnExchanges(): ?bool
    {
        return $this->has('charge_shipping_on_exchanges') ? $this->boolean('charge_shipping_on_exchanges') : null;
    }
}
