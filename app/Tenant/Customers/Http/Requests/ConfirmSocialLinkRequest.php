<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Proving the existing account is yours with its password, with the ticket the social sign-in answered.
 */
final class ConfirmSocialLinkRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'storefront';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The ticket from the sign-in's answer. */
            'ticket' => ['required', 'string', 'max:128'],
            /** The existing account's password. */
            'password' => ['required', 'string', 'max:128'],
            /** A label for the device, shown when the customer reviews where they are signed in. */
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function ticket(): string
    {
        return $this->string('ticket')->value();
    }

    public function password(): string
    {
        return $this->string('password')->value();
    }

    public function deviceName(): string
    {
        return $this->string('device_name', self::DEFAULT_DEVICE_NAME)->value();
    }
}
