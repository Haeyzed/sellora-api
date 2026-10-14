<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A customer erasing their own account, confirmed with their current password.
 */
final class EraseCustomerRequest extends FormRequest
{
    use ActsAsCustomer;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
        ];
    }
}
