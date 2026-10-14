<?php

declare(strict_types=1);

use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| For PromotionsTest.php and the other promotion tests
|--------------------------------------------------------------------------
*/

/**
 * Sends a request to the "promotions-store" store's staff promotions API, under /api/v1/staff/promotions, as a staff member (the test's owner when left out) or with a token.
 *
 * @param  array<string, mixed>  $data
 */
function promotionRequest(string $method, string $path, array $data = [], StaffMember|string|null $as = null): TestResponse
{
    forgetSignIns();
    $as ??= test()->owner;
    $token = $as instanceof StaffMember ? staffTokenFor($as, 'promotions-store') : $as;

    $response = test()->withToken($token)->json($method, storeUrl('promotions-store', '/api/v1/staff/promotions'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * The fields of a running automatic promotion taking 10% off the order, with any overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function percentOffOrder(array $overrides = []): array
{
    return [...['name' => 'Ten off', 'title' => ['en' => '10% off'], 'method' => 'automatic', 'type' => 'percentage', 'applies_to' => 'order', 'percent' => '10'], ...$overrides];
}

/**
 * Lets the current store sell in another currency, at a manual rate (units of it per unit of the base currency).
 */
function offerStoreCurrency(string $currency, string $rate = '1'): void
{
    DB::table('store_currencies')->insert(['currency_code' => $currency, 'is_active' => true, 'margin_percent' => 0, 'manual_rate' => $rate, 'manual_rate_set_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
}
