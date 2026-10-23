<?php

declare(strict_types=1);

use App\Landlord\Integrations\Models\WebhookEndpoint;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Shared\Auth\Models\Role;
use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Shared\Privacy\StoreExportRegistry;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Payments\Enums\PaymentPermission;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\PaymentGatewayResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Payments\FakePaymentGateway;
use Tests\Fixtures\Payments\LocalFakePaymentGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15.1: stores connect their own gateway accounts. Credentials are
 * checked with the gateway, kept encrypted, and never shown, audited or
 * exported again. Connecting needs the gateway on the plan; replacing keys,
 * turning a gateway off and disconnecting are always allowed, so money in
 * motion is never stranded. Each connection gets a random webhook endpoint
 * on the central database, never the store ID.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
});

afterEach(function (): void {
    deleteAllStores();
});

it('lists every gateway the platform offers, with what it can do, the keys it needs and whether the plan includes it', function (): void {
    putFakeGatewayInState('locked');
    $gateways = collect(staffOrderRequest('GET', '/payment-gateways')->assertOk()->json('data'))->keyBy('key');

    expect($gateways[FakePaymentGateway::KEY])->toMatchArray([
        'currencies' => ['NGN', 'USD', 'KWD', 'JPY', 'GBP'],
        'countries' => null,
        'methods' => ['card', 'bank_transfer'],
        'credential_fields' => [['name' => 'secret_key', 'secret' => true], ['name' => 'public_key', 'secret' => false]],
        'availability' => 'locked',
        'connection' => null,
    ])->and($gateways[LocalFakePaymentGateway::KEY])->toMatchArray(['countries' => ['NG'], 'availability' => 'enabled']);
});

it('connects the store\'s own account once the gateway accepts the keys, keeping them encrypted and never showing them again', function (): void {
    $response = staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('test')])->assertCreated();

    expect($response->json('data'))->toMatchArray(['gateway' => 'fake_gateway', 'status' => 'connected', 'enabled' => true, 'mode' => 'test', 'credentials_hint' => '…6789'])
        ->and($response->json('data'))->not->toHaveKeys(['credentials', 'retired_credentials', 'test_endpoint_id', 'live_endpoint_id', 'account_id'])
        ->and($response->getContent())->not->toContain('sk_test_')
        ->and(staffOrderRequest('GET', '/payment-gateways')->getContent())->not->toContain('sk_test_');

    [$testEndpointId, $liveEndpointId] = $this->store->run(function (): array {
        $connection = PaymentGatewayConnection::query()->sole();

        expect(DB::table('payment_gateway_connections')->value('credentials'))->not->toContain('sk_test_')
            ->and($connection->credentials)->toBe(fakeGatewayCredentials('test'))
            ->and($connection->account_id)->toBe('acct_main')
            // Not even encrypted: audits never hold the keys.
            ->and(DB::table('audits')->where('auditable_type', 'payment_gateway_connection')->get()->flatMap(static fn (object $audit): array => array_keys((array) json_decode((string) $audit->new_values, true)))->all())->toContain('gateway')->not->toContain('credentials')
            ->and(Activity::query()->inLog('payments')->sole()->description)->toBe('Connected the payment gateway fake_gateway');

        return [(string) $connection->test_endpoint_id, (string) $connection->live_endpoint_id];
    });

    // A webhook URL per mode, for the gateway dashboard's test and live fields, each naming a random endpoint, never the store.
    expect($response->json('data.webhook_urls'))->toBe(['test' => centralUrl('/webhooks/payments/'.$testEndpointId), 'live' => centralUrl('/webhooks/payments/'.$liveEndpointId)])
        ->and(WebhookEndpoint::query()->orderBy('id')->get()->map->only(['public_id', 'tenant_id', 'integration'])->all())->toBe([
            ['public_id' => $testEndpointId, 'tenant_id' => $this->store->id, 'integration' => 'fake_gateway'],
            ['public_id' => $liveEndpointId, 'tenant_id' => $this->store->id, 'integration' => 'fake_gateway'],
        ])
        ->and($testEndpointId)->not->toBe($liveEndpointId);

    foreach ([$testEndpointId, $liveEndpointId] as $endpointId) {
        expect(mb_strlen($endpointId))->toBe(40)->and($endpointId)->not->toContain((string) $this->store->id);
    }
});

it('saves nothing when the gateway refuses the keys or doesn\'t answer, and checks them against the gateway\'s own fields', function (): void {
    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => ['secret_key' => 'not-a-real-key-at-all', 'public_key' => 'pk_test_0123456789']])
        ->assertUnprocessable()->assertJsonPath('code', 'payment_gateway_credentials_invalid')->assertJsonValidationErrors('credentials');

    $this->script->unavailable = true;
    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('test')])->assertServiceUnavailable()->assertJsonPath('code', 'payment_gateway_unreachable');
    $this->script->unavailable = false;

    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => ['public_key' => 'pk_test_0123456789']])->assertJsonValidationErrors('credentials.secret_key');
    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => [...fakeGatewayCredentials('test'), 'password' => 'x']])->assertJsonValidationErrors('credentials');
    staffOrderRequest('PUT', '/payment-gateways/unknown_gateway/connection', ['credentials' => fakeGatewayCredentials('test')])->assertNotFound()->assertJsonPath('code', 'payment_gateway_not_found');

    expect($this->store->run(static fn (): int => PaymentGatewayConnection::query()->count()))->toBe(0)
        ->and(WebhookEndpoint::query()->count())->toBe(0);
});

it('needs the gateway on the plan to connect it, but always lets the store replace its keys, turn it off or disconnect it', function (string $state): void {
    putFakeGatewayInState($state);
    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('test')])->assertForbidden()->assertJsonPath('code', 'feature_'.$state);

    putFakeGatewayInState('enabled');
    connectFakeGateway('test');
    putFakeGatewayInState($state);

    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('live', 'abcdefghij')])->assertOk()->assertJsonPath('data.mode', 'live');
    staffOrderRequest('PATCH', '/payment-gateways/fake_gateway/connection', ['enabled' => false])->assertOk()->assertJsonPath('data.enabled', false);
    staffOrderRequest('DELETE', '/payment-gateways/fake_gateway/connection')->assertOk()->assertJsonPath('data.status', 'disconnected');
})->with(['locked', 'disabled', 'suspended', 'unavailable']);

it('keeps the webhook endpoints when keys are replaced, removes keys and endpoints on disconnect with nothing in motion, and makes new endpoints on reconnecting', function (): void {
    $first = connectFakeGateway('test');
    $replaced = connectFakeGateway('live', 'abcdefghij');

    expect($replaced->only(['test_endpoint_id', 'live_endpoint_id']))->toBe($first->only(['test_endpoint_id', 'live_endpoint_id']))
        ->and($replaced->mode->value)->toBe('live')
        ->and($replaced->retired_credentials)->toBeNull()
        ->and($this->store->run(static fn (): array => Activity::query()->inLog('payments')->pluck('description')->all()))->toBe(['Connected the payment gateway fake_gateway', 'Replaced the credentials of the payment gateway fake_gateway']);

    staffOrderRequest('DELETE', '/payment-gateways/fake_gateway/connection')->assertOk()->assertJsonPath('data.credentials_hint', null)->assertJsonPath('data.webhook_urls', ['test' => null, 'live' => null]);
    $this->store->run(static function (): void {
        expect(PaymentGatewayConnection::query()->sole()->only(['credentials', 'test_endpoint_id', 'live_endpoint_id', 'enabled']))->toBe(['credentials' => null, 'test_endpoint_id' => null, 'live_endpoint_id' => null, 'enabled' => false]);
    });
    expect(WebhookEndpoint::query()->count())->toBe(0);

    staffOrderRequest('DELETE', '/payment-gateways/fake_gateway/connection')->assertConflict()->assertJsonPath('code', 'payment_gateway_not_connected');
    staffOrderRequest('PATCH', '/payment-gateways/fake_gateway/connection', ['enabled' => true])->assertConflict();

    $again = connectFakeGateway('test');
    expect($again->test_endpoint_id)->not->toBe($first->test_endpoint_id)
        ->and(WebhookEndpoint::query()->orderBy('id')->pluck('public_id')->all())->toBe([$again->test_endpoint_id, $again->live_endpoint_id]);
});

it('checks saved keys with the gateway on request', function (): void {
    connectFakeGateway('live');
    $this->travel(1)->hour();

    staffOrderRequest('POST', '/payment-gateways/fake_gateway/connection/checks')->assertOk()->assertExactJson(['data' => ['valid' => true, 'mode' => 'live']]);
    expect($this->script->timesCalled('checkCredentials'))->toBe(2)
        ->and($this->store->run(static fn (): string => (string) PaymentGatewayConnection::query()->sole()->checked_at?->toIso8601String()))->toBe(now()->toIso8601String());

    $this->script->unavailable = true;
    staffOrderRequest('POST', '/payment-gateways/fake_gateway/connection/checks')->assertServiceUnavailable();
});

it('offers for new payments only connected, enabled gateways on the plan that take the currency from the buyer\'s country in the order\'s mode, in the store\'s order', function (): void {
    connectFakeGateway('test');
    connectFakeGateway('test', gateway: LocalFakePaymentGateway::KEY);
    $offered = fn (string $currency, ?string $country, bool $isTest = true): array => $this->store->run(static fn (): array => array_map(static fn (PaymentGatewayConnection $connection): string => $connection->gateway, app(PaymentGatewayResolver::class)->forNewPayment($currency, $country, $isTest)));

    expect($offered('NGN', 'NG'))->toBe(['fake_gateway', 'local_fake_gateway'])
        ->and($offered('NGN', 'GH'))->toBe(['fake_gateway'])
        ->and($offered('USD', 'NG'))->toBe(['fake_gateway'])
        ->and($offered('EUR', 'NG'))->toBe([])
        ->and($offered('NGN', 'NG', isTest: false))->toBe([]);

    staffOrderRequest('PATCH', '/payment-gateways/local_fake_gateway/connection', ['priority' => 0])->assertOk();
    staffOrderRequest('PATCH', '/payment-gateways/fake_gateway/connection', ['priority' => 5])->assertOk();
    expect($offered('NGN', 'NG'))->toBe(['local_fake_gateway', 'fake_gateway'])
        ->and($this->store->run(static fn (): array => app(PaymentGatewayResolver::class)->forNewPayment('NGN', 'NG', true, 'ussd')))->toHaveCount(1);

    staffOrderRequest('PATCH', '/payment-gateways/local_fake_gateway/connection', ['enabled' => false])->assertOk();
    expect($offered('NGN', 'NG'))->toBe(['fake_gateway']);

    // A gateway taken off the plan is no longer offered, though it stays connected.
    putFakeGatewayInState('locked');
    expect($offered('NGN', 'NG'))->toBe([]);
});

it('lets staff with payments.view look, only payments.manage_gateways change, and nobody else even look', function (): void {
    connectFakeGateway('test');
    $looker = $this->store->run(static function (): StaffMember {
        $role = Role::findOrCreate('Payments viewer', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName(PaymentPermission::PaymentsView->value, StaffMember::GUARD));
        $looker = StaffMember::factory()->create();
        $looker->assignRole($role);

        return $looker;
    });

    staffOrderRequest('GET', '/payment-gateways', as: $looker)->assertOk();
    staffOrderRequest('GET', '/payment-gateways', as: $this->viewer)->assertForbidden();

    foreach ([['PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('live')]], ['PATCH', '/payment-gateways/fake_gateway/connection', ['enabled' => false]], ['DELETE', '/payment-gateways/fake_gateway/connection', []], ['POST', '/payment-gateways/fake_gateway/connection/checks', []]] as [$method, $path, $data]) {
        staffOrderRequest($method, $path, $data, as: $looker)->assertForbidden();
    }

    expect($this->store->run(static fn (): bool => PaymentGatewayConnection::query()->sole()->enabled))->toBeTrue();
});

it('refuses a connected gateway without keys, mode or the endpoint of its mode, and endpoints without keys, at the database', function (): void {
    connectFakeGateway('test');

    $this->store->run(static function (): void {
        expect(static fn () => DB::table('payment_gateway_connections')->update(['mode' => 'sandbox']))->toThrow(QueryException::class, 'payment_gateway_connections_mode_check')
            ->and(static fn () => DB::table('payment_gateway_connections')->update(['test_endpoint_id' => null]))->toThrow(QueryException::class, 'payment_gateway_connections_connected_has_credentials')
            ->and(static fn () => DB::table('payment_gateway_connections')->update(['connected_at' => null]))->toThrow(QueryException::class, 'payment_gateway_connections_connected_at')
            ->and(static fn () => DB::table('payment_gateway_connections')->update(['status' => 'disconnected', 'connected_at' => null, 'disconnected_at' => now(), 'credentials' => null]))->toThrow(QueryException::class, 'payment_gateway_connections_keys_lead_webhooks');

        // Only the endpoint of its own mode is required.
        DB::table('payment_gateway_connections')->update(['live_endpoint_id' => null]);
    });
});

it('leads webhooks only to stores that serve requests, and never shows or changes another store\'s gateways', function (): void {
    $endpoint = (string) connectFakeGateway('test')->test_endpoint_id;
    $other = createStore('other-store', ['country_code' => 'NG']);
    $other->run(static fn () => PaymentGatewayConnection::query()->count());

    expect(app(WebhookEndpoints::class)->find($endpoint)?->tenantId)->toBe($this->store->id)
        ->and(app(WebhookEndpoints::class)->find('no-such-endpoint'))->toBeNull()
        ->and($other->run(static fn (): int => PaymentGatewayConnection::query()->count()))->toBe(0);

    $this->store->update(['status' => TenantStatus::Suspended]);
    expect(app(WebhookEndpoints::class)->find($endpoint)?->integration)->toBe('fake_gateway');

    closeStoreForPurging($this->store, now()->addDays(90));
    expect(app(WebhookEndpoints::class)->find($endpoint))->toBeNull();
});

it('never puts gateway keys or webhook endpoints in a store export', function (): void {
    $exported = app(StoreExportRegistry::class)->tables()['payment_gateway_connections']->exportedColumns();

    expect($exported)->toContain('gateway', 'mode', 'credentials_hint')
        ->not->toContain('credentials')
        ->not->toContain('retired_credentials')
        ->not->toContain('test_endpoint_id')
        ->not->toContain('live_endpoint_id');
});
