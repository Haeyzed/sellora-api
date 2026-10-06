<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Controllers;

use App\Shared\Auth\Models\Role;
use App\Shared\Http\Controller;
use App\Tenant\Identity\Actions\CreateStaffRole;
use App\Tenant\Identity\Actions\DeleteStaffRole;
use App\Tenant\Identity\Actions\UpdateStaffRole;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\RoleInUseException;
use App\Tenant\Identity\Exceptions\RoleNameTakenException;
use App\Tenant\Identity\Exceptions\RoleProtectedException;
use App\Tenant\Identity\Http\Requests\DeleteStaffRoleRequest;
use App\Tenant\Identity\Http\Requests\ListStaffRolesRequest;
use App\Tenant\Identity\Http\Requests\SaveStaffRoleRequest;
use App\Tenant\Identity\Http\Requests\ViewStaffRolesRequest;
use App\Tenant\Identity\Http\Resources\StaffPermissionResource;
use App\Tenant\Identity\Http\Resources\StaffRoleResource;
use App\Tenant\Identity\StaffPermissionCatalogue;
use App\Tenant\Identity\StaffRoleQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's roles, and the permissions they can grant.
 */
final class StaffRoleController extends Controller
{
    /**
     * List roles.
     *
     * Needs the roles.view permission. Sorted by name, with how many staff
     * members have each.
     */
    public function index(ListStaffRolesRequest $request): AnonymousResourceCollection
    {
        $roles = StaffRoleQueries::withStaffCounts()->orderBy('name')->orderBy('id')->cursorPaginate($request->perPage());

        return StaffRoleResource::collection($roles);
    }

    /**
     * Show a role.
     *
     * Needs the roles.view permission.
     */
    public function show(ViewStaffRolesRequest $request, Role $staffRole): StaffRoleResource
    {
        return new StaffRoleResource(StaffRoleQueries::withStaffCounts()->whereKey($staffRole->getKey())->firstOrFail());
    }

    /**
     * Create a role.
     *
     * Needs the roles.manage permission. A role can only grant permissions you
     * have yourself.
     *
     * @throws RoleNameTakenException
     * @throws PermissionsExceedYourOwnException
     */
    public function store(SaveStaffRoleRequest $request, CreateStaffRole $createStaffRole): JsonResponse
    {
        $role = $createStaffRole->handle($request->actor(), $request->roleName(), $request->permissionNames());

        return (new StaffRoleResource($role->load('permissions')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a role.
     *
     * Needs the roles.manage permission. Sends the name and the complete set
     * of permissions; changes apply at once to everyone with the role. You
     * can't change the Owner role, or a role with permissions you don't have.
     *
     * @throws RoleProtectedException
     * @throws RoleNameTakenException
     * @throws PermissionsExceedYourOwnException
     */
    public function update(SaveStaffRoleRequest $request, Role $staffRole, UpdateStaffRole $updateStaffRole): StaffRoleResource
    {
        $role = $updateStaffRole->handle($request->actor(), $staffRole, $request->roleName(), $request->permissionNames());

        return new StaffRoleResource($role->load('permissions'));
    }

    /**
     * Delete a role.
     *
     * Needs the roles.manage permission. Only roles that no staff member or
     * pending invitation has can be deleted. The Owner role can't be.
     *
     * @throws RoleProtectedException
     * @throws RoleInUseException
     * @throws PermissionsExceedYourOwnException
     */
    public function destroy(DeleteStaffRoleRequest $request, Role $staffRole, DeleteStaffRole $deleteStaffRole): Response
    {
        $deleteStaffRole->handle($request->actor(), $staffRole);

        return response()->noContent();
    }

    /**
     * List permissions roles can grant.
     *
     * Needs the roles.view permission. Each permission has a description in
     * the request's language.
     */
    public function permissions(ViewStaffRolesRequest $request, StaffPermissionCatalogue $staffPermissionCatalogue): AnonymousResourceCollection
    {
        return StaffPermissionResource::collection($staffPermissionCatalogue->all());
    }
}
