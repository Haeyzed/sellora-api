<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Controllers;

use App\Landlord\Identity\Actions\ChangePlatformAdminPermissions;
use App\Landlord\Identity\Actions\ChangePlatformAdminRoles;
use App\Landlord\Identity\Actions\DeactivatePlatformAdmin;
use App\Landlord\Identity\Actions\ReactivatePlatformAdmin;
use App\Landlord\Identity\Actions\ResetPlatformAdminTwoFactor;
use App\Landlord\Identity\Exceptions\CannotManageOwnPlatformAccountException;
use App\Landlord\Identity\Exceptions\LastSuperAdminException;
use App\Landlord\Identity\Exceptions\SuperAdminProtectedException;
use App\Landlord\Identity\Exceptions\TwoFactorNotSetUpException;
use App\Landlord\Identity\Http\Requests\ChangePlatformAdminPermissionsRequest;
use App\Landlord\Identity\Http\Requests\ChangePlatformAdminRolesRequest;
use App\Landlord\Identity\Http\Requests\ListPlatformTeamRequest;
use App\Landlord\Identity\Http\Requests\ManagePlatformTeamRequest;
use App\Landlord\Identity\Http\Requests\ResetPlatformAdminTwoFactorRequest;
use App\Landlord\Identity\Http\Resources\PlatformTeamMemberResource;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Services\PlatformAdminPermissions;
use App\Shared\Auth\EffectivePermissions;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\Http\Resources\EffectivePermissionsResource;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The members of Sellora's team. Super admins only.
 */
final class PlatformTeamMemberController extends Controller
{
    public function __construct(private readonly PlatformAdminPermissions $platformAdminPermissions) {}

    /**
     * List platform admins.
     *
     * Super admins only. Sorted by name, deactivated admins included.
     */
    public function index(ListPlatformTeamRequest $request): AnonymousResourceCollection
    {
        $platformAdmins = PlatformAdmin::query()->with('roles')->orderBy('name')->orderBy('id')->cursorPaginate($request->perPage());

        return PlatformTeamMemberResource::collection($platformAdmins);
    }

    /**
     * Show a platform admin.
     *
     * Super admins only.
     */
    public function show(ManagePlatformTeamRequest $request, PlatformAdmin $platformAdmin): PlatformTeamMemberResource
    {
        return new PlatformTeamMemberResource($platformAdmin->load('roles'));
    }

    /**
     * Change a platform admin's roles.
     *
     * Super admins only. Sends the complete set; it applies on their next
     * request. You can't change your own roles, and the last active super
     * admin keeps the role.
     *
     * @throws CannotManageOwnPlatformAccountException
     * @throws LastSuperAdminException
     */
    public function updateRoles(ChangePlatformAdminRolesRequest $request, PlatformAdmin $platformAdmin, ChangePlatformAdminRoles $changePlatformAdminRoles): PlatformTeamMemberResource
    {
        return new PlatformTeamMemberResource($changePlatformAdminRoles->handle($request->actor(), $platformAdmin, $request->roles()));
    }

    /**
     * Show what a platform admin may do, and why.
     *
     * Super admins only. Lists every permission they hold with where it comes
     * from: each role that gives it, and whether it was given directly. A
     * super admin may do everything.
     */
    public function permissions(ManagePlatformTeamRequest $request, PlatformAdmin $platformAdmin): EffectivePermissionsResource
    {
        return new EffectivePermissionsResource($this->effectivePermissionsOf($platformAdmin));
    }

    /**
     * Change a platform admin's direct permissions.
     *
     * Super admins only. Sends the complete set of permissions given directly,
     * on top of their roles (an empty list removes them all); it applies on
     * their next request. You can't change your own, and a super admin
     * already may do everything. Answers with what they may now do.
     *
     * @throws CannotManageOwnPlatformAccountException
     * @throws SuperAdminProtectedException
     */
    public function updatePermissions(ChangePlatformAdminPermissionsRequest $request, PlatformAdmin $platformAdmin, ChangePlatformAdminPermissions $changePlatformAdminPermissions): EffectivePermissionsResource
    {
        $changePlatformAdminPermissions->handle($request->actor(), $platformAdmin, $request->platformPermissions());

        return new EffectivePermissionsResource($this->effectivePermissionsOf($platformAdmin));
    }

    /**
     * Deactivate a platform admin.
     *
     * Super admins only. Signs them out everywhere at once. You can't
     * deactivate yourself or the last active super admin.
     *
     * @throws CannotManageOwnPlatformAccountException
     * @throws LastSuperAdminException
     */
    public function deactivate(ManagePlatformTeamRequest $request, PlatformAdmin $platformAdmin, DeactivatePlatformAdmin $deactivatePlatformAdmin): PlatformTeamMemberResource
    {
        return new PlatformTeamMemberResource($deactivatePlatformAdmin->handle($request->actor(), $platformAdmin)->load('roles'));
    }

    /**
     * Reactivate a platform admin.
     *
     * Super admins only. They can sign in again, with the roles they had.
     *
     * @throws CannotManageOwnPlatformAccountException
     */
    public function reactivate(ManagePlatformTeamRequest $request, PlatformAdmin $platformAdmin, ReactivatePlatformAdmin $reactivatePlatformAdmin): PlatformTeamMemberResource
    {
        return new PlatformTeamMemberResource($reactivatePlatformAdmin->handle($request->actor(), $platformAdmin)->load('roles'));
    }

    /**
     * Reset a platform admin's two-factor authentication.
     *
     * Super admins only, with your own password, for an admin who lost both
     * their phone and recovery codes. Signs them out everywhere; at their next
     * sign-in they can only set two-factor authentication up again. You can't
     * reset your own.
     *
     * @throws CannotManageOwnPlatformAccountException
     * @throws TooManyIncorrectAttemptsException
     * @throws IncorrectCurrentPasswordException
     * @throws TwoFactorNotSetUpException
     */
    public function resetTwoFactor(ResetPlatformAdminTwoFactorRequest $request, PlatformAdmin $platformAdmin, ResetPlatformAdminTwoFactor $resetPlatformAdminTwoFactor): PlatformTeamMemberResource
    {
        return new PlatformTeamMemberResource($resetPlatformAdminTwoFactor->handle($request->actor(), $platformAdmin, $request->actorPassword())->load('roles'));
    }

    private function effectivePermissionsOf(PlatformAdmin $platformAdmin): EffectivePermissions
    {
        return $this->platformAdminPermissions->effectivePermissionsOf($platformAdmin);
    }
}
