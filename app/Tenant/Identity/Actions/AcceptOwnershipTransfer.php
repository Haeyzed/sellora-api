<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\Models\Role;
use App\Shared\Tenancy\Contracts\StoreOwnerContact;
use App\Shared\Tenancy\Exceptions\TermsOfServiceNotAcceptedException;
use App\Tenant\Identity\Enums\OwnershipTransferStatus;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Exceptions\OwnershipTransferNotPendingException;
use App\Tenant\Identity\Jobs\SyncStoreOwnerContact;
use App\Tenant\Identity\Models\OwnershipTransfer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\OwnershipTransferredNotification;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The chosen colleague takes over the store: they become the owner, and the previous owner keeps only the roles they chose.
 *
 * The transfer row is locked while it is used, so it is accepted once even
 * under racing requests, and a cancellation can't slip in halfway. The store
 * database is the source of truth (section 6): the terms of service version
 * is checked with the platform first, the store commits the handover, and
 * then a queued job updates the platform's owner contact and records the
 * acceptance, retrying until it succeeds.
 */
final readonly class AcceptOwnershipTransfer
{
    public function __construct(private StoreOwnerContact $storeOwnerContact) {}

    /**
     * @param  string  $acceptedTermsOfServiceId  Public ID of the terms of service version the new owner accepts.
     *
     * @throws AuthorizationException When the signed-in staff member isn't the one the store was offered to.
     * @throws OwnershipTransferNotPendingException When it was already accepted, cancelled or has expired.
     * @throws TermsOfServiceNotAcceptedException When that isn't the terms of service version in force.
     */
    public function handle(StaffMember $recipient, OwnershipTransfer $ownershipTransfer, string $acceptedTermsOfServiceId, ?string $ipAddress, ?string $userAgent): OwnershipTransfer
    {
        $this->storeOwnerContact->ensureTermsOfServiceInForce($acceptedTermsOfServiceId);

        $ownershipTransfer = OwnershipTransfer::query()->getConnection()->transaction(function () use ($recipient, $ownershipTransfer, $acceptedTermsOfServiceId, $ipAddress, $userAgent): OwnershipTransfer {
            $locked = OwnershipTransfer::query()->whereKey($ownershipTransfer->id)->lockForUpdate()->firstOrFail();

            if ($locked->to_staff_member_id !== $recipient->id) {
                throw new AuthorizationException;
            }

            if (! $locked->isPending() || ! $recipient->is_active) {
                throw new OwnershipTransferNotPendingException;
            }

            $previousOwner = $locked->fromStaffMember;
            $locked->forceFill([
                'accepted_terms_of_service_id' => $acceptedTermsOfServiceId,
                'acceptance_ip_address' => $ipAddress,
                'acceptance_user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 512),
            ]);
            $this->handOver($locked, $previousOwner, $recipient);
            $this->notifyBoth($previousOwner, $recipient);
            dispatch(new SyncStoreOwnerContact($locked->id))->afterCommit();

            return $locked;
        });

        return $ownershipTransfer->load(['fromStaffMember', 'toStaffMember', 'keptRoles']);
    }

    private function handOver(OwnershipTransfer $ownershipTransfer, StaffMember $previousOwner, StaffMember $newOwner): void
    {
        $keptRoles = $ownershipTransfer->keptRoles->reject(static fn (Role $role): bool => $role->name === StaffRole::Owner->value);

        $newOwner->syncRoles([StaffRole::Owner->value]);
        $previousOwner->syncRoles($keptRoles);

        $ownershipTransfer->forceFill(['status' => OwnershipTransferStatus::Accepted, 'accepted_at' => CarbonImmutable::now()])->save();

        activity('team')
            ->causedBy($newOwner)
            ->performedOn($ownershipTransfer)
            ->event('ownership_transferred')
            ->withProperties(['from' => $previousOwner->public_id, 'to' => $newOwner->public_id, 'kept_roles' => array_values($keptRoles->map(static fn (Role $role): string => $role->name)->all())])
            ->log("{$newOwner->name} became the owner of the store, taking over from {$previousOwner->name}");
    }

    private function notifyBoth(StaffMember $previousOwner, StaffMember $newOwner): void
    {
        $storeName = tenant('name');
        $storeName = is_string($storeName) ? $storeName : '';

        $newOwner->notify(new OwnershipTransferredNotification($storeName, $newOwner->name, toNewOwner: true));
        $previousOwner->notify(new OwnershipTransferredNotification($storeName, $newOwner->name, toNewOwner: false));
    }
}
