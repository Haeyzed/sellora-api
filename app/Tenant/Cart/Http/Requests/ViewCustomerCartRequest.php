<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Requests;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff looking at a customer's cart, to help them. Needs customers.view; staff never change a cart.
 */
final class ViewCustomerCartRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
