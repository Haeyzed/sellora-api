<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A 6-digit code from the person's authenticator app.
 */
final class TwoFactorCodeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** The 6-digit code currently shown in the authenticator app. */
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->value();
    }
}
