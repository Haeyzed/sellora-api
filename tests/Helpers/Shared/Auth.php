<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| For ExpiredPasswordResetTokenRetentionTest.php
|--------------------------------------------------------------------------
*/

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
