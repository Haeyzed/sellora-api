<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Jobs;

use App\Shared\Tenancy\Contracts\StoreOwnerContact;
use App\Shared\Tenancy\TermsOfServiceAcceptance;
use App\Tenant\Identity\Enums\OwnershipTransferStatus;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\OwnershipTransfer;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Brings the platform's owner contact up to date with the store's owner, and records a new owner's acceptance of the terms of service.
 *
 * Dispatched in the store's context once the store's change has committed
 * (section 6), when ownership is transferred or the owner's name or email
 * changes. It reads the owner as they are when it runs, not as they were
 * when it was queued, so it is safe to run twice or out of order, and it
 * retries until the platform accepts it.
 */
final class SyncStoreOwnerContact implements ShouldQueue
{
    use Queueable;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900, 3600];

    /**
     * @param  int|null  $ownershipTransferId  The accepted transfer whose terms of service acceptance to record, if any.
     */
    public function __construct(public readonly ?int $ownershipTransferId = null) {}

    /**
     * Keeps retrying for a week; a platform outage longer than that needs a person anyway.
     */
    public function retryUntil(): DateTimeInterface
    {
        return CarbonImmutable::now()->addWeek();
    }

    public function handle(StoreOwnerContact $storeOwnerContact): void
    {
        $owner = StaffMember::query()->role(StaffRole::Owner->value)->orderBy('id')->first();

        if ($owner === null) {
            return;
        }

        $storeOwnerContact->update($owner->name, $owner->email, $this->termsOfServiceAcceptance());
    }

    private function termsOfServiceAcceptance(): ?TermsOfServiceAcceptance
    {
        if ($this->ownershipTransferId === null) {
            return null;
        }

        $ownershipTransfer = OwnershipTransfer::query()->with('toStaffMember')->find($this->ownershipTransferId);

        if ($ownershipTransfer?->status !== OwnershipTransferStatus::Accepted
            || $ownershipTransfer->accepted_at === null
            || $ownershipTransfer->accepted_terms_of_service_id === null) {
            return null;
        }

        return new TermsOfServiceAcceptance(
            reference: 'ownership_transfer:'.$ownershipTransfer->public_id,
            termsOfServiceId: $ownershipTransfer->accepted_terms_of_service_id,
            name: $ownershipTransfer->toStaffMember->name,
            email: $ownershipTransfer->toStaffMember->email,
            ipAddress: $ownershipTransfer->acceptance_ip_address,
            userAgent: $ownershipTransfer->acceptance_user_agent,
            acceptedAt: $ownershipTransfer->accepted_at,
        );
    }
}
