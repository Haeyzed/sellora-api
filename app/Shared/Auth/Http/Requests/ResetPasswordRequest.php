<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * The details from a password reset link, plus the new password.
 */
final class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, list<string|ValidationRule|Password>>
     */
    public function rules(): array
    {
        return [
            /** The token from the reset link. */
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function normalisedEmail(): string
    {
        return mb_strtolower(trim($this->string('email')->value()));
    }
}
