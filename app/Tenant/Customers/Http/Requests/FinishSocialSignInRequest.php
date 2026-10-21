<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What the storefront's sign-in callback page read from the URL fragment the relay sent it to: the one-time state and the provider's code.
 */
final class FinishSocialSignInRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'storefront';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The "state" from the URL fragment. */
            'state' => ['required', 'string', 'max:128'],
            /** The "code" from the URL fragment. */
            'code' => ['required', 'string', 'max:2048'],
            /** A label for the device, shown when the customer reviews where they are signed in. */
            'device_name' => ['sometimes', 'string', 'max:100'],
            /** For Apple: the "name" from the URL fragment, which Apple sends only the first time, unsigned. Used only as the new account's display name: letters, marks, spaces, apostrophes, full stops and hyphens, up to 120. */
            'name' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[\pL\pM][\pL\pM\s\'.-]*$/u'],
        ];
    }

    /**
     * The display name the storefront passed on from Apple's unsigned answer, if any.
     */
    public function displayName(): ?string
    {
        return $this->filled('name') ? trim($this->string('name')->value()) : null;
    }

    public function state(): string
    {
        return $this->string('state')->value();
    }

    public function deviceName(): string
    {
        return $this->string('device_name', self::DEFAULT_DEVICE_NAME)->value();
    }
}
