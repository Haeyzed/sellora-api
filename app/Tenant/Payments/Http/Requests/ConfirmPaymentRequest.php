<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asking a payment's gateway again where it stands. Needs the payments.confirm permission.
 */
final class ConfirmPaymentRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('confirm', Payment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
