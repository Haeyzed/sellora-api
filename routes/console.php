<?php

declare(strict_types=1);

use App\Shared\Retention\PurgeExpiredRecordsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(PurgeExpiredRecordsCommand::class)
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping();
