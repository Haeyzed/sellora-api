<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use App\Shared\Http\PaginatedListRequest;

/**
 * A page of the platform team: admins, invitations or roles. Super admins only.
 */
final class ListPlatformTeamRequest extends PaginatedListRequest
{
    use ActsAsSuperAdmin;
}
