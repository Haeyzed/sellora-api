<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\AccessTokenIssuer;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Foundation\Testing\DatabaseTruncation;

/*
 * Section 10: one guard's token must never authorize another guard's routes,
 * tested for every pair. Platform tokens live in the central database and
 * store tokens in each store's database, so the IDs of different guards'
 * tokens overlap; only the secret part and the owner type keep them apart.
 */
uses(DatabaseTruncation::class);

afterEach(function (): void {
    deleteAllStores();
});

it('accepts each guard\'s token only on that guard\'s routes', function (): void {
    $store = createStore('first-store');
    $issuer = app(AccessTokenIssuer::class);
    $tokens = [
        'platform' => $issuer->issue(PlatformAdmin::factory()->create(), PlatformAdmin::GUARD, 'test')->plainTextToken,
        ...$store->run(static fn (): array => [
            'staff' => $issuer->issue(StaffMember::factory()->create(), StaffMember::GUARD, 'test')->plainTextToken,
            'customer' => $issuer->issue(Customer::factory()->create(), Customer::GUARD, 'test')->plainTextToken,
            'driver' => $issuer->issue(Driver::factory()->create(), Driver::GUARD, 'test')->plainTextToken,
        ]),
    ];

    $meEndpoints = [
        'platform' => '/api/v1/platform/auth/me',
        'staff' => storeUrl('first-store', '/api/v1/staff/auth/me'),
        'customer' => storeUrl('first-store', '/api/v1/customer/auth/me'),
        'driver' => storeUrl('first-store', '/api/v1/driver/auth/me'),
    ];

    foreach ($tokens as $tokenGuard => $token) {
        foreach ($meEndpoints as $routeGuard => $endpoint) {
            forgetSignIns();
            $expectedStatus = $tokenGuard === $routeGuard ? 200 : 401;

            $status = $this->withToken($token)->getJson($endpoint)->status();
            tenancy()->end();

            expect($status)->toBe($expectedStatus, "A {$tokenGuard} token on the {$routeGuard} route");
        }
    }
});

it('stops a deactivated account\'s existing tokens working on the very next request, for every guard', function (): void {
    $store = createStore('first-store');
    $issuer = app(AccessTokenIssuer::class);
    $platformAdmin = PlatformAdmin::factory()->create();
    $signedIn = [
        ['/api/v1/platform/auth/me', $issuer->issue($platformAdmin, PlatformAdmin::GUARD, 'test')->plainTextToken],
        ...$store->run(static fn (): array => array_map(
            static fn (array $account): array => [storeUrl('first-store', $account[1]), $issuer->issue($account[0], $account[0]::GUARD, 'test')->plainTextToken],
            [
                [StaffMember::factory()->create(), '/api/v1/staff/auth/me'],
                [Customer::factory()->create(), '/api/v1/customer/auth/me'],
                [Driver::factory()->create(), '/api/v1/driver/auth/me'],
            ],
        )),
    ];

    $platformAdmin->update(['is_active' => false]);
    $store->run(static function (): void {
        StaffMember::query()->update(['is_active' => false]);
        Customer::query()->update(['is_active' => false]);
        Driver::query()->update(['is_active' => false]);
    });

    foreach ($signedIn as [$endpoint, $token]) {
        forgetSignIns();
        $status = $this->withToken($token)->getJson($endpoint)->status();
        tenancy()->end();

        expect($status)->toBe(401, "A deactivated account's token on {$endpoint}");
    }
});
