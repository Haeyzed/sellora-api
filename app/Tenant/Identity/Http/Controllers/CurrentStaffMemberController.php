<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Identity\Http\Resources\StaffMemberResource;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Http\Request;
use LogicException;

/**
 * The signed-in staff member's own account.
 */
final class CurrentStaffMemberController extends Controller
{
    /**
     * Get the signed-in staff member.
     *
     * Includes their roles and every permission they hold in this store, so the
     * dashboard can show only what they may use.
     */
    public function __invoke(Request $request): StaffMemberResource
    {
        $staffMember = $request->user();

        if (! $staffMember instanceof StaffMember) {
            throw new LogicException('This route must be protected by auth:staff.');
        }

        return new StaffMemberResource($staffMember);
    }
}
