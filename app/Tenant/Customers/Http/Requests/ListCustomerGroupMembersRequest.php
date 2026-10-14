<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;

/**
 * A page of the customers in one group, most recently added first. Needs customers.view.
 */
final class ListCustomerGroupMembersRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', CustomerGroup::class);
    }
}
