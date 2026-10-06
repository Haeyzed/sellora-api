<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Proof needed to turn two-factor authentication off: the current password and either an authenticator code or a recovery code.
 */
final class DisableTwoFactorRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:128'],
            /** The 6-digit code currently shown in the authenticator app. */
            'code' => ['required_without:recovery_code', 'prohibits:recovery_code', 'string', 'digits:6'],
            /** One of the recovery codes, when the authenticator app is not available. */
            'recovery_code' => ['required_without:code', 'string', 'max:32'],
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
}
