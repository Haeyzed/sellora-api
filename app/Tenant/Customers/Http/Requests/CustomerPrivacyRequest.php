<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff answering a customer's data-protection request: downloading all their personal data, or erasing it (with the staff member's own password). Needs customers.privacy.
 */
final class CustomerPrivacyRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('handlePrivacyRequests', Customer::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->isMethod('POST')
            ? [
                /** Your own password, to confirm an erasure that can't be undone. */
                'password' => ['required', 'string'],
            ]
            : [];
    }
}
