<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The super admin's own password, confirming a two-factor reset for someone else.
 */
final class ResetPlatformAdminTwoFactorRequest extends FormRequest
{
    use ActsAsSuperAdmin;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Your own current password. */
            'password' => ['required', 'string', 'max:128'],
        ];
    }

    public function actorPassword(): string
    {
        return $this->string('password')->value();
    }
}
