<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The owner confirming, with their password (and a code with two-factor authentication), that they want to close the store.
 */
final class CloseOwnStoreRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('closeStore');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:128'],
            /** The 6-digit authenticator code. Required when you use two-factor authentication, unless you send a recovery code. */
            'code' => ['nullable', 'prohibits:recovery_code', 'string', 'digits:6'],
            /** One of your recovery codes, when the authenticator app is not available. */
            'recovery_code' => ['nullable', 'string', 'max:32'],
            /** Why you are closing the store, for Sellora's team. Optional. A business reason only: never personal data such as health, family or contact details, because the platform keeps it in its activity and audit logs until their retention period ends. */
            'reason' => ['nullable', 'string', 'max:500'],
        ];
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

    public function reason(): ?string
    {
        return $this->filled('reason') ? trim($this->string('reason')->value()) : null;
    }
}
