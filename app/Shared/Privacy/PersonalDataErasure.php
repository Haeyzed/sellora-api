<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

use Closure;
use Illuminate\Database\DatabaseManager;

/**
 * Runs erasure, or retention anonymisation, in one transaction that tells the database it is erasing personal data (section 3.3).
 *
 * Triggers on records otherwise never changed or deleted (marketing consent
 * events, orders' contact details) let erasure through only while the
 * transaction has app.erasing_personal_data on. This is the only code that
 * sets it: domain handlers never do. The setting is cleared again before the
 * work's transaction ends, so it never outlives the work, even inside a
 * larger transaction.
 */
final readonly class PersonalDataErasure
{
    /** Named only here and in migrations; a test checks that no other code does. */
    public const string SETTING = 'app.erasing_personal_data';

    public function __construct(private DatabaseManager $databases) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult
     */
    public function run(Closure $work): mixed
    {
        $connection = $this->databases->connection();

        return $connection->transaction(static function () use ($connection, $work): mixed {
            $connection->select('select set_config(?, ?, true)', [self::SETTING, 'on']);
            $result = $work();
            $connection->select('select set_config(?, ?, true)', [self::SETTING, '']);

            return $result;
        });
    }
}
