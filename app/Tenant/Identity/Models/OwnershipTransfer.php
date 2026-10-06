<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Models;

use App\Shared\Auth\Models\Role;
use App\Shared\Concerns\HasPublicId;
use App\Tenant\Identity\Enums\OwnershipTransferStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The owner's offer to hand the store to a colleague, which changes nothing until that colleague accepts it.
 *
 * @property int $id
 * @property string $public_id
 * @property int $from_staff_member_id
 * @property int $to_staff_member_id
 * @property OwnershipTransferStatus $status As stored; see currentStatus() for one that has run out of time.
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read StaffMember $fromStaffMember
 * @property-read StaffMember $toStaffMember
 * @property-read Collection<int, Role> $keptRoles
 */
final class OwnershipTransfer extends Model
{
    use HasPublicId;

    protected $fillable = [
        'expires_at',
    ];

    /**
     * Whether it can still be accepted or cancelled: pending and not past its time.
     */
    public function isPending(): bool
    {
        return $this->status === OwnershipTransferStatus::Pending && $this->expires_at->isFuture();
    }

    /**
     * The status as it is now: a pending transfer past its time is expired, even before it is marked so.
     */
    public function currentStatus(): OwnershipTransferStatus
    {
        if ($this->status === OwnershipTransferStatus::Pending && ! $this->expires_at->isFuture()) {
            return OwnershipTransferStatus::Expired;
        }

        return $this->status;
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function fromStaffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'from_staff_member_id');
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function toStaffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'to_staff_member_id');
    }

    /**
     * The roles the current owner keeps after handing the store over.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function keptRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'ownership_transfer_kept_roles');
    }

    /**
     * Transfers stored as pending, including ones past their time not yet marked expired.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeMarkedPending(Builder $query): void
    {
        $query->where('status', OwnershipTransferStatus::Pending);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OwnershipTransferStatus::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
