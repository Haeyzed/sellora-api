<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use App\Shared\Http\PageListRequest;

/**
 * A page of the platform team: admins, invitations or roles. Super admins only.
 */
final class ListPlatformTeamRequest extends PageListRequest
{
    use ActsAsSuperAdmin;
}
