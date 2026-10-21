<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A customer erasing their own account: confirmed with their current password, or, without one, with a one-time erasure confirmation.
 */
final class EraseCustomerRequest extends FormRequest
{
    use ActsAsCustomer;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $hasPassword = fn (): bool => $this->customer()->password !== null;
        $hasNoPassword = fn (): bool => ! $hasPassword();

        return [
            /** Your current password, when your account has one. */
            'current_password' => [Rule::requiredIf($hasPassword), Rule::prohibitedIf($hasNoPassword), 'string'],
            /** Without a password: the token from the erasure email's link, or from signing in again with a connected provider. */
            'erasure_token' => [Rule::requiredIf($hasNoPassword), Rule::prohibitedIf($hasPassword), 'string', 'max:128'],
        ];
    }

    public function currentPassword(): ?string
    {
        return $this->has('current_password') ? $this->string('current_password')->value() : null;
    }

    public function erasureToken(): ?string
    {
        return $this->has('erasure_token') ? $this->string('erasure_token')->value() : null;
    }
}
