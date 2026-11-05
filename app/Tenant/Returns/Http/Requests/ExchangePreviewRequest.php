<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Shipping\Models\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff asking what a return's exchange would come to now. Needs the returns.view permission.
 */
final class ExchangePreviewRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('view', OrderReturn::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** The shipping method to send the replacement with; the way the order was sent when left out. */
            'shipping_method' => ['sometimes', 'string', Rule::exists(ShippingMethod::class, 'public_id')->whereNull('archived_at')],
        ];
    }

    public function shippingMethod(): ?ShippingMethod
    {
        $method = $this->validated('shipping_method');

        return is_string($method) ? ShippingMethod::query()->where('public_id', $method)->sole() : null;
    }
}
