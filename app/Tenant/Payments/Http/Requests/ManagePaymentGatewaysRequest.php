<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Disconnecting or checking a payment gateway. Needs the payments.manage_gateways permission.
 */
final class ManagePaymentGatewaysRequest extends FormRequest
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
        return [];
    }
}
