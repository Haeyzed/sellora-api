<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;

/**
 * A page of the staff notes about one customer, newest first. Needs customers.view.
 */
final class ListCustomerNotesRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Customer::class);
    }
}
