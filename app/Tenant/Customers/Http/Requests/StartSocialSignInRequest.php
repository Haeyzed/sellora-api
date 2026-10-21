<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Starting a social sign-in from the storefront, optionally saying where in the storefront to return once it finishes.
 */
final class StartSocialSignInRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** A path in the storefront to return to once signed in, such as "/checkout": it must start with a single "/", and is handed back when the sign-in finishes, never redirected to by the platform. */
            'return_path' => ['sometimes', 'nullable', 'string', 'max:512', 'regex:/^\/(?![\/\\\\])[^\s\\\\]*$/'],
        ];
    }

    public function returnPath(): ?string
    {
        return $this->filled('return_path') ? $this->string('return_path')->value() : null;
    }
}
