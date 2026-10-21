<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirming, from the emailed link, that a sign-in provider may be connected to the account.
 */
final class ConfirmSocialLinkEmailRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'storefront';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The token from the emailed link. */
            'token' => ['required', 'string', 'max:128'],
            /** A label for the device, shown when the customer reviews where they are signed in. */
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function token(): string
    {
        return $this->string('token')->value();
    }

    public function deviceName(): string
    {
        return $this->string('device_name', self::DEFAULT_DEVICE_NAME)->value();
    }
}
