<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RateLimitServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    Integrations\OpenExchangeRates\OpenExchangeRatesServiceProvider::class,
    Integrations\Paystack\PaystackServiceProvider::class,
];
