<?php

declare(strict_types=1);

namespace App\Shared\Retention;

use Illuminate\Console\Command;

/**
 * Starts the daily clean-up of expired personal data, for the platform and for every store.
 *
 * It only queues the work: one job for the central database and one job per
 * store, on the bulk queue.
 */
final class PurgeExpiredRecordsCommand extends Command
{
    protected $signature = 'retention:purge';

    protected $description = 'Queue the purge of records past their retention period, for the platform and every store';

    /**
     * Queues one purge job for the central database and one for each store.
     */
    public function handle(): int
    {
        PurgeExpiredRecords::dispatch();

        tenancy()->runForMultiple(null, static function (): void {
            PurgeExpiredRecords::dispatch();
        });

        $this->components->info('Retention purges queued.');

        return self::SUCCESS;
    }
}
