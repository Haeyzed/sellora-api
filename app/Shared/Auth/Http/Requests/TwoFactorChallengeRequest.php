<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The answer to a two-factor challenge: the challenge token from sign-in, and either an authenticator code or a recovery code.
 */
final class TwoFactorChallengeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** The challenge_token returned by sign-in. */
            'challenge_token' => ['required', 'string', 'size:64'],
            /** The 6-digit code currently shown in the authenticator app. */
            'code' => ['required_without:recovery_code', 'prohibits:recovery_code', 'string', 'digits:6'],
            /** One of the recovery codes, when the authenticator app is not available. Each works once. */
            'recovery_code' => ['required_without:code', 'string', 'max:32'],
        ];
    }

    public function challengeToken(): string
    {
        return $this->string('challenge_token')->value();
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
