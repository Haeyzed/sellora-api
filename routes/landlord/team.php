<?php

declare(strict_types=1);

use App\Landlord\Identity\Http\Controllers\PlatformAdminInvitationController;
use App\Landlord\Identity\Http\Controllers\PlatformRoleController;
use App\Landlord\Identity\Http\Controllers\PlatformTeamMemberController;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Shared\Auth\Models\Role;
use Illuminate\Support\Facades\Route;

/*
| Sellora's own team (Landlord\Identity), under /api/v1/platform/team:
| admins, invitations, roles and permissions. Super admins only.
*/

Route::bind('platformRole', static fn (string $publicId): Role => Role::query()
    ->where('guard_name', PlatformAdmin::GUARD)
    ->where('public_id', $publicId)
    ->firstOrFail());

Route::prefix('team')
    ->name('team.')
    ->middleware(['auth:'.PlatformAdmin::GUARD, EnsureTwoFactorIsEnabled::class, 'throttle:api'])
    ->group(static function (): void {
        Route::get('admins', [PlatformTeamMemberController::class, 'index'])->name('admins.index');
        Route::get('admins/{platformAdmin}', [PlatformTeamMemberController::class, 'show'])->name('admins.show');
        Route::put('admins/{platformAdmin}/roles', [PlatformTeamMemberController::class, 'updateRoles'])->name('admins.roles.update');
        Route::post('admins/{platformAdmin}/deactivate', [PlatformTeamMemberController::class, 'deactivate'])->name('admins.deactivate');
        Route::post('admins/{platformAdmin}/reactivate', [PlatformTeamMemberController::class, 'reactivate'])->name('admins.reactivate');
        Route::post('admins/{platformAdmin}/two-factor-reset', [PlatformTeamMemberController::class, 'resetTwoFactor'])->name('admins.two-factor-reset');

        Route::get('invitations', [PlatformAdminInvitationController::class, 'index'])->name('invitations.index');
        Route::post('invitations', [PlatformAdminInvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{platformAdminInvitation}/resend', [PlatformAdminInvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('invitations/{platformAdminInvitation}', [PlatformAdminInvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::get('roles', [PlatformRoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [PlatformRoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{platformRole}', [PlatformRoleController::class, 'show'])->name('roles.show');
        Route::put('roles/{platformRole}', [PlatformRoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{platformRole}', [PlatformRoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('permissions', [PlatformRoleController::class, 'permissions'])->name('permissions.index');
    });
