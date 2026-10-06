<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

/**
 * The details a shopper gives to create an account at a store.
 */
final class RegisterCustomerRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'Unnamed device';

    /**
     * @return array<string, list<string|ValidationRule|Password|Unique>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique(Customer::class, 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            /** A label for the device, shown when the shopper reviews where they are signed in. */
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function deviceName(): string
    {
        return $this->string('device_name', self::DEFAULT_DEVICE_NAME)->value();
    }

    /**
     * Checks the email in the form accounts are stored with, so "Ada@Mail.com" can't register twice.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->string('email')->value()))]);
        }
    }
}
