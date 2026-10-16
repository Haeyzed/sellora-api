<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff answering a guest's data-protection request, for orders placed without an account: downloading their personal data, or erasing it (with the staff member's own password). Needs customers.privacy.
 *
 * A guest has no account to prove who they are, so staff check the request
 * came from the email's owner (for example by replying to it) first.
 */
final class GuestPrivacyRequest extends FormRequest
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
        return [
            /** The email the guest placed their orders with. */
            'email' => ['required', 'string', 'email', 'max:254'],
            ...($this->routeIs('staff.guest-personal-data.erasure.store') ? [
                /** Your own password, to confirm an erasure that can't be undone. */
                'password' => ['required', 'string'],
            ] : []),
        ];
    }

    public function email(): string
    {
        return mb_strtolower($this->string('email')->trim()->value());
    }
}
