<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Shipping\Models\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff confirming a return's exchange as they previewed it. Needs the returns.exchange permission (sensitive: it places an order and settles money), and payments.refund too, since any difference is refunded.
 */
final class ConfirmExchangeRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('exchange', OrderReturn::class)
            && $this->actor()->can('refund', Payment::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** The quote_hash of the preview being confirmed: if anything charged changed since, nothing is placed and the new preview comes back (409). */
            'quote_hash' => ['required', 'string', 'size:64'],
            /** The shipping method previewed; the way the order was sent when left out. */
            'shipping_method' => ['sometimes', 'string', Rule::exists(ShippingMethod::class, 'public_id')->whereNull('archived_at')],
        ];
    }

    public function quoteHash(): string
    {
        return $this->string('quote_hash')->value();
    }

    public function shippingMethod(): ?ShippingMethod
    {
        $method = $this->validated('shipping_method');

        return is_string($method) ? ShippingMethod::query()->where('public_id', $method)->sole() : null;
    }
}
