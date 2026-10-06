<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The email address someone wants a password reset link sent to.
 */
final class SendPasswordResetLinkRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
        ];
    }

    public function normalisedEmail(): string
    {
        return mb_strtolower(trim($this->string('email')->value()));
    }
}
