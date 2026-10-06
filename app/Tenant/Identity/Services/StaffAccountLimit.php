<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Services;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\Features;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\DatabaseManager;
use LogicException;

/**
 * The plan's limit on staff accounts beyond the owner. Active staff members and pending invitations both count, so a store can't invite past its limit.
 */
final readonly class StaffAccountLimit
{
    public const string LIMIT_KEY = 'staff_accounts';

    public function __construct(
        private Features $features,
        private DatabaseManager $database,
    ) {}

    /**
     * Checks there is room for one more account, holding a lock until the caller's transaction ends so two requests can't both take the last place.
     *
     * Must run inside a database transaction. The lock is per store, because
     * each store has its own database.
     *
     * @param  StaffInvitation|null  $replacing  An invitation being turned into the new account (accepted), which already holds a place.
     *
     * @throws UsageLimitReachedException When the store's plan has no room left.
     * @throws LogicException When called outside a transaction, where the lock would release at once.
     */
    public function ensureRoomForOneMore(?StaffInvitation $replacing = null): void
    {
        $connection = $this->database->connection();

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('The staff account limit must be checked inside a database transaction.');
        }

        // A PostgreSQL transaction-level advisory lock: released automatically at commit or rollback.
        $connection->select('select pg_advisory_xact_lock(hashtext(?))', [self::LIMIT_KEY]);

        $pendingInvitations = StaffInvitation::query()->pending();

        if ($replacing !== null) {
            $pendingInvitations->whereKeyNot($replacing->getKey());
        }

        // The owner comes with every store, so the limit only covers additional staff.
        $additionalStaff = StaffMember::query()
            ->where('is_active', true)
            ->withoutRole(StaffRole::Owner->value, StaffMember::GUARD)
            ->count();

        $usage = $additionalStaff + $pendingInvitations->count();

        $this->features->ensureWithinLimit(self::LIMIT_KEY, $usage);
    }
}
