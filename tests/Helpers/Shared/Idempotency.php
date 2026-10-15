<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;

/*
|--------------------------------------------------------------------------
| For EnsureRequestIsIdempotentTest.php
|--------------------------------------------------------------------------
*/

/**
 * Sends a sample charge with an Idempotency-Key: as a guest with a cart token (by default), as a signed-in account of a guard, or as nobody known.
 *
 * @param  array<string, mixed>  $body
 * @param  array{string, int}|null  $as  A guard and an account id to be signed in as.
 */
function chargeWithKey(string $key, array $body = ['amount' => 1000], string $ip = '203.0.113.10', ?string $guest = 'guest-a', ?array $as = null): Illuminate\Testing\TestResponse
{
    forgetSignIns();
    $request = test()->withHeader('Idempotency-Key', $key)->withServerVariables(['REMOTE_ADDR' => $ip]);

    if ($guest !== null) {
        $request = $request->withHeader('X-Cart-Token', str_pad($guest, 43, 'x'));
    }

    if ($as !== null) {
        $request = $request->actingAs(new GenericUser(['id' => $as[1]]), $as[0]);
    }

    $response = $request->postJson('/api/v1/sample-charges', $body);
    test()->flushHeaders();
    forgetSignIns();

    return $response;
}
