<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The signed-in person's current password, asked again before a sensitive change such as two-factor settings.
 */
final class CurrentPasswordRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:128'],
        ];
    }

    public function currentPassword(): string
    {
        return $this->string('current_password')->value();
    }
}
