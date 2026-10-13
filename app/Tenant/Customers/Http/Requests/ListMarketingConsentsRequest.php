<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;

/**
 * A page of a customer's marketing consent history, newest first. Needs customers.view.
 */
final class ListMarketingConsentsRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Customer::class);
    }
}
