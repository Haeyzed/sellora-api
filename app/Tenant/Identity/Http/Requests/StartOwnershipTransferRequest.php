<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Models\OwnershipTransfer;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Who the owner offers the store to, the roles the owner keeps, and the proof that it really is the owner.
 */
final class StartOwnershipTransferRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ChoosesStaffRoles;

    public function authorize(): bool
    {
        return $this->actor()->can('create', OwnershipTransfer::class);
    }

    /**
     * @return array<string, list<string|ValidationRule|Exists>>
     */
    public function rules(): array
    {
        return [
            /** Public ID of the active staff member who will become the owner. */
            'staff_member' => ['required', 'string', Rule::exists(StaffMember::class, 'public_id')],
            'current_password' => ['required', 'string', 'max:128'],
            /** The 6-digit authenticator code. Required when you use two-factor authentication, unless you send a recovery code. */
            'code' => ['nullable', 'prohibits:recovery_code', 'string', 'digits:6'],
            /** One of your recovery codes, when the authenticator app is not available. */
            'recovery_code' => ['nullable', 'string', 'max:32'],
            ...$this->roleRules(),
        ];
    }

    public function recipient(): StaffMember
    {
        return StaffMember::query()->where('public_id', $this->string('staff_member')->value())->firstOrFail();
    }

    public function currentPassword(): string
    {
        return $this->string('current_password')->value();
    }

    public function code(): ?string
    {
        return $this->filled('code') ? $this->string('code')->value() : null;
    }

    public function recoveryCode(): ?string
    {
        return $this->filled('recovery_code') ? $this->string('recovery_code')->value() : null;
    }
}
