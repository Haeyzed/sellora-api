<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The owner requiring two-factor authentication for staff, or no longer requiring it. Only the owner may.
 */
final class ChangeStaffTwoFactorRequirementRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('requireStaffTwoFactor', StoreSettings::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** True to require two-factor authentication for every staff member; you must use it yourself first. */
            'required' => ['required', 'boolean'],
            'current_password' => ['required', 'string', 'max:128'],
            /** The 6-digit authenticator code. Required when you use two-factor authentication, unless you send a recovery code. */
            'code' => ['nullable', 'prohibits:recovery_code', 'string', 'digits:6'],
            /** One of your recovery codes, when the authenticator app is not available. */
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function isRequired(): bool
    {
        return $this->boolean('required');
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
