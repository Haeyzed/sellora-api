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
            /** Whether a buyer must add a photo when the reason is damaged or the wrong item. */
            'photos_required' => ['sometimes', 'boolean:strict'],
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
}
