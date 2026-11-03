<?php

declare(strict_types=1);

use App\Shared\Exceptions\DomainException;
use App\Shared\Retention\PurgeExpiredRecords;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TruncatesCentralDatabase;

/*
 * The queue worker saves a failed job's error as it was thrown, so the error
 * stored in failed_jobs must be scrubbed on its own; and failed jobs, which
 * keep each job's data, are purged after their retention period.
 */

uses(TruncatesCentralDatabase::class);

/**
 * A queued job that fails on a database error quoting an email. The email is written in the job, not given to it, so the stored job data doesn't hold it.
 */
final class SaveAdminWithoutNameJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        DB::table('platform_admins')->insert(['public_id' => (string) Str::ulid(), 'email' => 'ada@buyer.test', 'password' => 'not-a-hash']);
    }
}

/**
 * The same, failing on a business rule error that the database error caused, as code that turns a duplicate into a clear error does.
 */
final class SaveAdminWithoutNameAsBusinessRuleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        try {
            (new SaveAdminWithoutNameJob)->handle();
        } catch (QueryException $exception) {
            throw new class(previous: $exception) extends DomainException
            {
                public function errorCode(): string
                {
                    return 'sample_rule_broken';
                }
            };
        }
    }
}

it('stores a job that failed on a business rule error without the email of the database error that caused it', function (): void {
    SaveAdminWithoutNameAsBusinessRuleJob::dispatch()->onConnection('database');

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

    expect(DB::table('failed_jobs')->sole()->exception)->not->toContain('ada@buyer.test')
        ->toContain('caused by database error SQLSTATE[23502]');
});

it('stores a job that failed on a database error without the email the error quoted', function (): void {
    SaveAdminWithoutNameJob::dispatch()->onConnection('database');

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

    $failedJob = DB::table('failed_jobs')->sole();
    expect($failedJob->exception)->not->toContain('ada@buyer.test')
        ->and($failedJob->exception)->not->toContain('insert into')
        ->and($failedJob->exception)->toContain('database error SQLSTATE[23502]');
});

it('deletes failed jobs older than their retention period and keeps newer ones', function (): void {
    config(['retention.periods.failed_jobs' => 30]);
    foreach (['failed-long-ago' => now()->subDays(31), 'failed-recently' => now()->subDays(29)] as $uuid => $failedAt) {
        DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => $failedAt]);
    }

    PurgeExpiredRecords::dispatchSync();

    expect(DB::table('failed_jobs')->pluck('uuid')->all())->toBe(['failed-recently']);
});

it('keeps failed jobs for 30 days unless configured otherwise', function (): void {
    expect(config('retention.periods.failed_jobs'))->toBe(30);
});
