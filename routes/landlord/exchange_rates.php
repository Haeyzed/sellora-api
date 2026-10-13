<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Shared\Money\ExchangeRates\Http\Controllers\ExchangeRateController;
use Illuminate\Support\Facades\Route;

/*
| The market exchange rates fetched for every store (App\Shared\Money\ExchangeRates),
| under /api/v1/platform/exchange-rates. Any platform admin may read them.
*/

Route::middleware(['auth:'.PlatformAdmin::GUARD, EnsureTwoFactorIsEnabled::class, 'throttle:api'])
    ->group(static function (): void {
        Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
    });
