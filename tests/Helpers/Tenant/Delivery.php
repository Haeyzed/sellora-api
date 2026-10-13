<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| For DriverAuthTest.php
|--------------------------------------------------------------------------
*/

function signInAsDriver(string $phone, string $pin): TestResponse
{
    $response = test()->postJson(storeUrl('first-store', '/api/v1/driver/auth/tokens'), ['phone' => $phone, 'pin' => $pin]);
    tenancy()->end();
    forgetSignIns();

    return $response;
}
