<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The token from a platform admin invitation link, sent in the body so it never appears in server logs.
 */
final class PlatformAdminInvitationTokenRequest extends FormRequest
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
