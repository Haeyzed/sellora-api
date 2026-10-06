<?php

declare(strict_types=1);

use App\Shared\Retention\PurgeExpiredRecords;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

/*
 * Password reset requests hold an email address, so the daily purge deletes
 * them once their link has been expired for the whole retention period: the
 * platform's in the central database, staff and customers' in each store's.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    config([
        'retention.periods.expired_password_reset_tokens' => 1,
        'auth.passwords.platform_admins.expire' => 60,
        'auth.passwords.staff_members.expire' => 60,
        'auth.passwords.customers.expire' => 60,
    ]);
});

afterEach(function (): void {
    deleteAllStores();
});

function createPasswordResetRequest(string $table, string $email, Carbon\CarbonInterface $createdAt): void
{
    DB::table($table)->insert(['email' => $email, 'token' => bcrypt('token'), 'created_at' => $createdAt]);
}

/**
 * @return list<string>
 */
function remainingPasswordResetEmails(string $table): array
{
    return DB::table($table)->orderBy('email')->pluck('email')->all();
}

it('deletes platform admin reset requests only after their link has been expired for the whole retention period', function (): void {
    createPasswordResetRequest('platform_admin_password_reset_tokens', 'old@example.com', now()->subDay()->subHours(2));
    createPasswordResetRequest('platform_admin_password_reset_tokens', 'recent@example.com', now()->subHours(12));

    PurgeExpiredRecords::dispatchSync();

    expect(remainingPasswordResetEmails('platform_admin_password_reset_tokens'))->toBe(['recent@example.com']);
});

it('deletes staff and customer reset requests in each store\'s own database', function (): void {
    $store = createStore('first-store');

    $store->run(static function (): void {
        foreach (['staff_member_password_reset_tokens', 'customer_password_reset_tokens'] as $table) {
            createPasswordResetRequest($table, 'old@example.com', now()->subDay()->subHours(2));
            createPasswordResetRequest($table, 'recent@example.com', now()->subHours(12));
        }

        PurgeExpiredRecords::dispatchSync();

        expect(remainingPasswordResetEmails('staff_member_password_reset_tokens'))->toBe(['recent@example.com'])
            ->and(remainingPasswordResetEmails('customer_password_reset_tokens'))->toBe(['recent@example.com']);
    });
});
