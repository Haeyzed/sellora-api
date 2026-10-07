<?php

declare(strict_types=1);

use App\Shared\Geography\Http\Controllers\GeographyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Geography API
|--------------------------------------------------------------------------
|
| Read-only reference data for forms (App\Shared\Geography), under
| /api/v1/geography on the central domain (the sign-up form) and on every
| store's domain (address forms), without signing in. The same for
| everyone, so browsers and proxies may cache it for an hour.
|
*/

Route::middleware(['throttle:public', 'cache.headers:public;max_age=3600;etag'])
    ->prefix('geography')
    ->group(static function (): void {
        Route::get('countries', [GeographyController::class, 'countries'])->name('countries.index');
        Route::get('countries/{country}', [GeographyController::class, 'country'])->name('countries.show')->where('country', '[A-Za-z]{2}');
        Route::get('countries/{country}/states', [GeographyController::class, 'states'])->name('countries.states.index')->where('country', '[A-Za-z]{2}');
        Route::get('currencies', [GeographyController::class, 'currencies'])->name('currencies.index');
        Route::get('timezones', [GeographyController::class, 'timezones'])->name('timezones.index');
        Route::get('locales', [GeographyController::class, 'locales'])->name('locales.index');
    });
