<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff deleting a customer group or taking a customer out of one. Needs customers.manage.
 */
final class ManageCustomerGroupRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', CustomerGroup::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
