<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The token from an emailed invitation link.
 */
final class StaffInvitationTokenRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** The token from the invitation link. */
            'token' => ['required', 'string', 'size:64'],
        ];
    }

    public function token(): string
    {
        return $this->string('token')->value();
    }
}
