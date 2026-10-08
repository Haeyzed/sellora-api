<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Http\Controllers\OwnershipTransferController;
use App\Tenant\Identity\Http\Controllers\StaffInvitationController;
use App\Tenant\Identity\Http\Controllers\StaffRoleController;
use App\Tenant\Identity\Http\Controllers\TeamMemberController;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\Route;

/*
| The store's team (Tenant\Identity), under /api/v1/staff/team: members,
| invitations, roles, permissions and ownership transfers. Each endpoint
| checks its own permission.
*/

Route::bind('staffRole', static fn (string $publicId): Role => Role::query()
    ->where('guard_name', StaffMember::GUARD)
    ->where('public_id', $publicId)
    ->firstOrFail());

Route::prefix('staff/team')
    ->name('staff.team.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api'])
    ->group(static function (): void {
        Route::get('members', [TeamMemberController::class, 'index'])->name('members.index');
        Route::get('members/{staffMember}', [TeamMemberController::class, 'show'])->name('members.show');
        Route::put('members/{staffMember}/roles', [TeamMemberController::class, 'updateRoles'])->name('members.roles.update');
        Route::get('members/{staffMember}/permissions', [TeamMemberController::class, 'permissions'])->name('members.permissions.show');
        Route::put('members/{staffMember}/permissions', [TeamMemberController::class, 'updatePermissions'])->name('members.permissions.update');
        Route::post('members/{staffMember}/deactivate', [TeamMemberController::class, 'deactivate'])->name('members.deactivate');
        Route::post('members/{staffMember}/reactivate', [TeamMemberController::class, 'reactivate'])->name('members.reactivate');

        Route::get('invitations', [StaffInvitationController::class, 'index'])->name('invitations.index');
        Route::post('invitations', [StaffInvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{staffInvitation}/resend', [StaffInvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('invitations/{staffInvitation}', [StaffInvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::get('roles', [StaffRoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [StaffRoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{staffRole}', [StaffRoleController::class, 'show'])->name('roles.show');
        Route::put('roles/{staffRole}', [StaffRoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{staffRole}', [StaffRoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('permissions', [StaffRoleController::class, 'permissions'])->name('permissions.index');

        Route::get('ownership-transfers/pending', [OwnershipTransferController::class, 'pending'])->name('ownership-transfers.pending');
        Route::post('ownership-transfers', [OwnershipTransferController::class, 'store'])->name('ownership-transfers.store');
        Route::delete('ownership-transfers/{ownershipTransfer}', [OwnershipTransferController::class, 'destroy'])->name('ownership-transfers.destroy');
        Route::post('ownership-transfers/{ownershipTransfer}/acceptance', [OwnershipTransferController::class, 'accept'])->name('ownership-transfers.accept');
    });
