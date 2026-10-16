<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Turning a connected gateway on or off for new payments, or moving it in the order gateways are offered. Needs the payments.manage_gateways permission.
 */
final class ChangePaymentGatewayConnectionRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', PaymentGatewayConnection::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Whether buyers are offered it for new payments. Turning it off never stops payments already under way. */
            'enabled' => ['sometimes', 'boolean:strict'],
            /** Lower is offered first. */
            'priority' => ['sometimes', 'integer:strict', 'between:0,1000'],
        ];
    }

    public function enabled(): ?bool
    {
        return $this->has('enabled') ? $this->boolean('enabled') : null;
    }

    public function priority(): ?int
    {
        return $this->has('priority') ? $this->integer('priority') : null;
    }
}
