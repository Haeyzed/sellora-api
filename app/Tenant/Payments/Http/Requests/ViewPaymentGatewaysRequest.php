<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Seeing the store's payment gateways and how they are connected. Needs the payments.view permission.
 */
final class ViewPaymentGatewaysRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', PaymentGatewayConnection::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
