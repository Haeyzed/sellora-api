<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Console\PurgeClosedStoresCommand;
use App\Landlord\Tenancy\Console\ReconcileStockLevelsCommand;
use App\Landlord\Tenancy\Console\ReleaseExpiredReservationsCommand;
use App\Landlord\Tenancy\Console\SendPurgeRemindersCommand;
use App\Shared\Money\ExchangeRates\RefreshExchangeRatesCommand;
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

// Does nothing until tenancy.purge_enabled is switched on (section 9.1).
Schedule::command(PurgeClosedStoresCommand::class)
    ->dailyAt('04:00')
    ->onOneServer()
    ->withoutOverlapping();

// Reports any stock level that doesn't match its movement ledger (section 13).
Schedule::command(ReconcileStockLevelsCommand::class)
    ->dailyAt('05:00')
    ->onOneServer()
    ->withoutOverlapping();

// Gives back checkout holds that ran out (section 13); reserving also does it for the variants it touches.
Schedule::command(ReleaseExpiredReservationsCommand::class)
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping();

// Market exchange rates for every store (section 3.3); alerts when none has been fetched for 12 hours.
Schedule::command(RefreshExchangeRatesCommand::class)
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();
