<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * The signed-in staff member making a request, for requests on routes behind auth:staff.
 *
 * @mixin FormRequest
 */
trait ActsAsStaffMember
{
    /**
     * @throws LogicException When the route isn't behind auth:staff, which is a routing mistake.
     */
    public function actor(): StaffMember
    {
        $actor = $this->user();

        if (! $actor instanceof StaffMember) {
            throw new LogicException('This route must be protected by the auth:staff middleware.');
        }

        return $actor;
    }
}
