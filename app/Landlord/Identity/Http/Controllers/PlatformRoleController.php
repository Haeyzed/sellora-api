<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Controllers;

use App\Landlord\Identity\Actions\CreatePlatformRole;
use App\Landlord\Identity\Actions\DeletePlatformRole;
use App\Landlord\Identity\Actions\UpdatePlatformRole;
use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Exceptions\PlatformRoleInUseException;
use App\Landlord\Identity\Exceptions\PlatformRoleNameTakenException;
use App\Landlord\Identity\Exceptions\PlatformRoleProtectedException;
use App\Landlord\Identity\Http\Requests\ListPlatformTeamRequest;
use App\Landlord\Identity\Http\Requests\ManagePlatformTeamRequest;
use App\Landlord\Identity\Http\Requests\SavePlatformRoleRequest;
use App\Landlord\Identity\Http\Resources\PlatformPermissionResource;
use App\Landlord\Identity\Http\Resources\PlatformRoleResource;
use App\Landlord\Identity\PlatformRoleQueries;
use App\Shared\Auth\Models\Role;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The platform's roles, and the permissions they can grant. Super admins only.
 */
final class PlatformRoleController extends Controller
{
    /**
     * List platform roles.
     *
     * Super admins only. Sorted by name, with how many admins have each.
     */
    public function index(ListPlatformTeamRequest $request): AnonymousResourceCollection
    {
        return PlatformRoleResource::collection(PlatformRoleQueries::withAdminCounts()->orderBy('name')->orderBy('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show a platform role.
     *
     * Super admins only.
     */
    public function show(ManagePlatformTeamRequest $request, Role $platformRole): PlatformRoleResource
    {
        return new PlatformRoleResource(PlatformRoleQueries::withAdminCounts()->whereKey($platformRole->getKey())->firstOrFail());
    }

    /**
     * Create a platform role.
     *
     * Super admins only.
     *
     * @throws PlatformRoleNameTakenException
     */
    public function store(SavePlatformRoleRequest $request, CreatePlatformRole $createPlatformRole): JsonResponse
    {
        $role = $createPlatformRole->handle($request->actor(), $request->roleName(), $request->permissions());

        return (new PlatformRoleResource($role->load('permissions')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a platform role.
     *
     * Super admins only. Sends the name and the complete set of permissions;
     * changes apply at once to everyone with the role. Super Admin can't be
     * changed.
     *
     * @throws PlatformRoleProtectedException
     * @throws PlatformRoleNameTakenException
     */
    public function update(SavePlatformRoleRequest $request, Role $platformRole, UpdatePlatformRole $updatePlatformRole): PlatformRoleResource
    {
        return new PlatformRoleResource($updatePlatformRole->handle($request->actor(), $platformRole, $request->roleName(), $request->permissions())->load('permissions'));
    }

    /**
     * Delete a platform role.
     *
     * Super admins only. Only roles no admin or pending invitation has can be
     * deleted. Super Admin can't be.
     *
     * @throws PlatformRoleProtectedException
     * @throws PlatformRoleInUseException
     */
    public function destroy(ManagePlatformTeamRequest $request, Role $platformRole, DeletePlatformRole $deletePlatformRole): Response
    {
        $deletePlatformRole->handle($request->actor(), $platformRole);

        return response()->noContent();
    }

    /**
     * List permissions platform roles can grant.
     *
     * Super admins only. Each permission has a description in the request's
     * language.
     */
    public function permissions(ManagePlatformTeamRequest $request): AnonymousResourceCollection
    {
        return PlatformPermissionResource::collection(PlatformPermission::cases());
    }
}
