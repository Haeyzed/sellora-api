<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Shared\Http\PaginatedListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Identity\Enums\StaffPermission;

/**
 * A page of the store's roles.
 */
final class ListStaffRolesRequest extends PaginatedListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can(StaffPermission::RolesView->value);
    }
}
