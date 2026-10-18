<?php

declare(strict_types=1);

use App\Landlord\Integrations\Console\CheckPendingPaymentsCommand;
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

// Gives back checkout holds that ran out, stock and promotion uses (section 13); reserving also does it for the variants or promotion it touches.
Schedule::command(ReleaseExpiredReservationsCommand::class)
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping();

// The safety net behind each payment's own checks with its gateway, and pruning of unused webhook endpoints (section 3.3).
Schedule::command(CheckPendingPaymentsCommand::class)
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

// Market exchange rates for every store (section 3.3); alerts when none has been fetched for 12 hours.
Schedule::command(RefreshExchangeRatesCommand::class)
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();
