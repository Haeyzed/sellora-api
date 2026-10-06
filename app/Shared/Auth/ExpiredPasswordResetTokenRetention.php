<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;

/**
 * Deletes password reset requests that stopped working a while ago, on the platform and in every store.
 *
 * Each request holds an email address and a hashed token, and is useless once
 * its link has expired (the broker's "expire" minutes in config/auth.php), so
 * it is purged once it has been expired for longer than its retention period.
 * Brokers with a "connection" are cleaned in the central database; the others
 * in each store's database. Only Sellora's own brokers (those with a
 * "reset_url") are cleaned: Laravel merges its unused "users" broker into the
 * config, and that table doesn't exist.
 */
final readonly class ExpiredPasswordResetTokenRetention implements RetentionPolicy
{
    public function __construct(
        private ConfigRepository $config,
        private DatabaseManager $database,
    ) {}

    public function periodKey(): string
    {
        return 'expired_password_reset_tokens';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Both;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $isTenantContext = tenancy()->initialized;
        $deletedCount = 0;

        foreach ($this->config->array('auth.passwords') as $broker) {
            if (! is_array($broker) || ! isset($broker['reset_url']) || ! is_string($broker['table'] ?? null) || ! is_int($broker['expire'] ?? null)) {
                continue;
            }

            $connection = is_string($broker['connection'] ?? null) ? $broker['connection'] : null;

            if (($connection === null) !== $isTenantContext) {
                continue;
            }

            $deletedCount += $this->database->connection($connection)
                ->table($broker['table'])
                ->where('created_at', '<', $cutoff->subMinutes($broker['expire']))
                ->delete();
        }

        return $deletedCount;
    }
}
