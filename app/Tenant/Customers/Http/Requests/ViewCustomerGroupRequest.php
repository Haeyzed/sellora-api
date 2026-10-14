<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff looking at one customer group. Needs customers.view.
 */
final class ViewCustomerGroupRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', CustomerGroup::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
