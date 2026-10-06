<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Console\SendPurgeRemindersCommand;
use App\Shared\Retention\PurgeExpiredRecordsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(PurgeExpiredRecordsCommand::class)
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command(SendPurgeRemindersCommand::class)
    ->dailyAt('09:00')
    ->onOneServer()
    ->withoutOverlapping();
