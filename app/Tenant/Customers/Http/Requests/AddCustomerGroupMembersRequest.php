<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Putting customers in a group, up to 100 at once. Needs customers.manage.
 */
final class AddCustomerGroupMembersRequest extends FormRequest
{
    use ActsAsStaffMember;

    public const int MAX_CUSTOMERS = 100;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', CustomerGroup::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The customers' IDs. Customers already in the group are left as they are. */
            'customers' => ['required', 'array', 'min:1', 'max:'.self::MAX_CUSTOMERS],
            'customers.*' => ['required', 'string', 'distinct', Rule::exists(Customer::class, 'public_id')->whereNull('deleted_at')->whereNull('erased_at')],
        ];
    }

    /**
     * @return list<Customer>
     */
    public function customers(): array
    {
        return array_values(Customer::query()->whereIn('public_id', $this->array('customers'))->orderBy('id')->get()->all());
    }
}
