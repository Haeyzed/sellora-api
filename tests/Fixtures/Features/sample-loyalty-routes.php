<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/loyalty-points', static fn (): array => ['points' => 120])->name('points.index');
Route::post('/loyalty-points', static fn (): array => ['points' => 130])->name('points.store');
Route::post('/loyalty-points/redeem', static fn (): array => ['points' => 0])->name('points.redeem');
