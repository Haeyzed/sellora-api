<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Accepting a platform admin invitation: the link's token, and the name and password the person chooses.
 */
final class AcceptPlatformAdminInvitationRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'Unnamed device';

    /**
     * @return array<string, list<string|ValidationRule|Password>>
     */
    public function rules(): array
    {
        return [
            /** The token from the invitation link. */
            'token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            /** A label for the device, shown when the person reviews where they are signed in. */
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function token(): string
    {
        return $this->string('token')->value();
    }

    public function chosenName(): string
    {
        return trim($this->string('name')->value());
    }

    public function chosenPassword(): string
    {
        return $this->string('password')->value();
    }

    public function deviceName(): string
    {
        return $this->string('device_name', self::DEFAULT_DEVICE_NAME)->value();
    }
}
