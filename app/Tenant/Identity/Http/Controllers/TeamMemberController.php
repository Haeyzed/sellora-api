<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Identity\Actions\ChangeStaffMemberRoles;
use App\Tenant\Identity\Actions\DeactivateStaffMember;
use App\Tenant\Identity\Actions\ReactivateStaffMember;
use App\Tenant\Identity\Exceptions\CannotManageOwnAccountException;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\RoleNotAssignableException;
use App\Tenant\Identity\Exceptions\StoreOwnerProtectedException;
use App\Tenant\Identity\Http\Requests\ChangeTeamMemberRolesRequest;
use App\Tenant\Identity\Http\Requests\ListTeamMembersRequest;
use App\Tenant\Identity\Http\Requests\ManageTeamMemberRequest;
use App\Tenant\Identity\Http\Requests\ViewTeamMemberRequest;
use App\Tenant\Identity\Http\Resources\TeamMemberResource;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The store's team: who is on it, their roles, and deactivating or reactivating them.
 */
final class TeamMemberController extends Controller
{
    /**
     * List team members.
     *
     * Needs the staff.view permission. Sorted by name.
     */
    public function index(ListTeamMembersRequest $request): AnonymousResourceCollection
    {
        $staffMembers = StaffMember::query()
            ->with('roles')
            ->when($request->activeFilter() !== null, static fn ($query) => $query->where('is_active', $request->activeFilter()))
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($request->perPage());

        return TeamMemberResource::collection($staffMembers);
    }

    /**
     * Show a team member.
     *
     * Needs the staff.view permission.
     */
    public function show(ViewTeamMemberRequest $request, StaffMember $staffMember): TeamMemberResource
    {
        return new TeamMemberResource($staffMember->load('roles'));
    }

    /**
     * Change a team member's roles.
     *
     * Needs the staff.manage permission. Sends the complete new set. You can't
     * change your own roles or the owner's, give the Owner role, or give or
     * take roles with permissions you don't have yourself.
     *
     * @throws CannotManageOwnAccountException
     * @throws StoreOwnerProtectedException
     * @throws PermissionsExceedYourOwnException
     * @throws RoleNotAssignableException
     */
    public function updateRoles(ChangeTeamMemberRolesRequest $request, StaffMember $staffMember, ChangeStaffMemberRoles $changeStaffMemberRoles): TeamMemberResource
    {
        return new TeamMemberResource($changeStaffMemberRoles->handle($request->actor(), $staffMember, $request->roles()));
    }

    /**
     * Deactivate a team member.
     *
     * Needs the staff.manage permission. They are signed out everywhere at
     * once and can't sign in; their history is kept. You can't deactivate
     * yourself, the owner, or someone with permissions you don't have.
     *
     * @throws CannotManageOwnAccountException
     * @throws StoreOwnerProtectedException
     * @throws PermissionsExceedYourOwnException
     */
    public function deactivate(ManageTeamMemberRequest $request, StaffMember $staffMember, DeactivateStaffMember $deactivateStaffMember): TeamMemberResource
    {
        return new TeamMemberResource($deactivateStaffMember->handle($request->actor(), $staffMember)->load('roles'));
    }

    /**
     * Reactivate a team member.
     *
     * Needs the staff.manage permission and room in the plan's staff limit.
     *
     * @throws CannotManageOwnAccountException
     * @throws StoreOwnerProtectedException
     * @throws PermissionsExceedYourOwnException
     * @throws UsageLimitReachedException
     */
    public function reactivate(ManageTeamMemberRequest $request, StaffMember $staffMember, ReactivateStaffMember $reactivateStaffMember): TeamMemberResource
    {
        return new TeamMemberResource($reactivateStaffMember->handle($request->actor(), $staffMember)->load('roles'));
    }
}
