<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * A customer's request to move their account to another email.
 */
final class StartCustomerEmailChangeRequest extends FormRequest
{
    use ActsAsCustomer;

    /**
     * @return array<string, list<string|ValidationRule|Unique>>
     */
    public function rules(): array
    {
        return [
            /** The new address, which no account may have yet, this one included. A link is sent there; nothing changes until it is used. */
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique(Customer::class, 'email')],
            'current_password' => ['required', 'string'],
        ];
    }

    public function newEmail(): string
    {
        return $this->string('email')->value();
    }

    /**
     * Checks the email in the form accounts are stored with.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->string('email')->value()))]);
        }
    }
}
