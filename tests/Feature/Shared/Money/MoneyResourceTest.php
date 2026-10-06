<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Shared\Money\MoneyResource;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

it('returns minor units, the currency code and a display string', function (): void {
    Route::get('/api/v1/test-money', static fn (): MoneyResource => new MoneyResource(Money::ofMinor(125000, 'USD')));

    $this->getJson('/api/v1/test-money')
        ->assertExactJson(['data' => ['amount' => 125000, 'currency' => 'USD', 'formatted' => '$1,250.00']]);
});

it('formats the display string in the request\'s language', function (): void {
    App::setLocale('de');

    $money = (new MoneyResource(Money::ofMinor(125000, 'EUR')))->resolve(request());

    expect($money['formatted'])->toBe("1.250,00\u{a0}€");
});
