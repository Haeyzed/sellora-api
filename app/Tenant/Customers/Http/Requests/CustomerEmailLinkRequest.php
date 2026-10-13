<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The token from a link emailed to a customer.
 */
final class CustomerEmailLinkRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** The token from the emailed link. */
            'token' => ['required', 'string', 'max:128'],
        ];
    }

    public function token(): string
    {
        return $this->string('token')->value();
    }
}
