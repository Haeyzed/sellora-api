<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The code from the email that confirms a sign-up.
 */
final class VerifyStoreRegistrationRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** The 6-digit code from the email. */
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->value();
    }
}
