<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sign-in details for accounts that sign in with an email address and password: platform admins, staff and customers.
 */
final class EmailSignInRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'Unnamed device';

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'max:128'],
            /** A label for the device, shown when the person reviews where they are signed in. */
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    /**
     * The email in the form accounts are stored with: trimmed and lower-cased.
     */
    public function normalisedEmail(): string
    {
        return mb_strtolower(trim($this->string('email')->value()));
    }

    public function deviceName(): string
    {
        return $this->string('device_name', self::DEFAULT_DEVICE_NAME)->value();
    }
}
