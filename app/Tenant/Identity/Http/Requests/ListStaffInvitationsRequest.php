<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Shared\Http\PaginatedListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Identity\Models\StaffInvitation;

/**
 * A page of the invitations that haven't been accepted or cancelled, pending and expired.
 */
final class ListStaffInvitationsRequest extends PaginatedListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', StaffInvitation::class);
    }
}
