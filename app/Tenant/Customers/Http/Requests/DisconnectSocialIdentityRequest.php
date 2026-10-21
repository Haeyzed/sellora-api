<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The signed-in customer disconnecting a provider: with their current password when the account has one.
 */
final class DisconnectSocialIdentityRequest extends FormRequest
{
    use ActsAsCustomer;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Your current password; needed when the account has one. */
            'current_password' => [$this->customer()->password !== null ? 'required' : 'sometimes', 'string', 'max:128'],
        ];
    }

    public function currentPassword(): ?string
    {
        return $this->filled('current_password') ? $this->string('current_password')->value() : null;
    }
}
