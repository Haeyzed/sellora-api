<?php

declare(strict_types=1);

use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Listeners\AlertPlatformAdminsWhenAttentionIsNeeded;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Services\PlatformAlertFromEvent;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Jobs\ProvisionStore;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\Models\Role;
use App\Shared\Money\ExchangeRates\Contracts\ExchangeRateSource;
use App\Shared\Tenancy\Contracts\StoreInvoiceBalances;
use App\Shared\Tenancy\Contracts\StoreStockLedger;
use App\Shared\Tenancy\InvoiceBalanceMismatch;
use App\Shared\Tenancy\StockLevelMismatch;
use Database\Seeders\Landlord\PlatformPermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\ExchangeRates\FakeExchangeRateSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 20a: platform alerts. Something on the platform side that Sellora's
 * team must look at (exchange rates not refreshed for 12 hours, a store's
 * setup failing for good, a scheduled check finding a store's stock or
 * invoices out of line) is emailed to every active super admin and every
 * active holder of stores.manage, each in their saved language. One that
 * may come again day after day is sent at most once a day (per store).
 */

uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformPermissionSeeder::class);
    $this->superAdmin = PlatformAdmin::factory()->create();
    $this->superAdmin->assignRole(Role::findOrCreate(PlatformRole::SuperAdmin->value, PlatformAdmin::GUARD));
    $this->manager = platformAdminWith('stores.manage');
    $this->manager->forceFill(['locale' => 'fr'])->save();
    $this->directManager = PlatformAdmin::factory()->create();
    $this->directManager->givePermissionTo('stores.manage');
    $this->viewer = platformAdminWith('stores.view');
    $this->formerManager = platformAdminWith('stores.manage');
    $this->formerManager->forceFill(['is_active' => false])->save();
    Notification::fake();
});

afterEach(function (): void {
    deleteAllStores();
});

it('listens for every event that raises a platform alert', function (): void {
    $listened = array_values(array_filter(PlatformAlertFromEvent::EVENTS, static fn (string $event): bool => in_array(AlertPlatformAdminsWhenAttentionIsNeeded::class, array_map(
        static fn (mixed $listener): string => is_string($listener) ? $listener : '',
        app('events')->getRawListeners()[$event] ?? [],
    ), true)));

    expect($listened)->toBe(PlatformAlertFromEvent::EVENTS)->toHaveCount(4);
});

it('emails exchange rates not refreshed to active super admins and holders of stores.manage, each in their language, once a day', function (): void {
    $source = new FakeExchangeRateSource;
    $source->fails = true;
    app()->instance(ExchangeRateSource::class, $source);

    Artisan::call('exchange-rates:refresh');
    Artisan::call('exchange-rates:refresh');

    expect(platformAlertEmailsSent())->toBe([
        [$this->superAdmin->email, 'en', 'Exchange rates not refreshed for 12 hours', 'No exchange rate fetch has succeeded for at least 12 hours. Stores stop offering their extra currencies once rates are 48 hours old. Check the exchange rate provider.'],
        [$this->manager->email, 'fr', 'Taux de change non actualisés depuis 12 heures', 'Aucune récupération des taux de change n\'a réussi depuis au moins 12 heures. Les boutiques cessent de proposer leurs devises supplémentaires lorsque les taux ont 48 heures. Vérifiez le fournisseur de taux de change.'],
        [$this->directManager->email, 'en', 'Exchange rates not refreshed for 12 hours', 'No exchange rate fetch has succeeded for at least 12 hours. Stores stop offering their extra currencies once rates are 48 hours old. Check the exchange rate provider.'],
    ]);

    $this->travel(1)->days();
    Artisan::call('exchange-rates:refresh');

    expect(platformAlertEmailsSent())->toHaveCount(6);
});

it('emails a store\'s setup failing for good, naming the store, each time it fails', function (): void {
    $store = Tenant::factory()->provisioning()->create(['name' => 'Ada Shoes']);

    (new ProvisionStore($store->id))->failed(new RuntimeException('No database server.'));
    // Already marked as failed: nothing new happened, so nobody is told again.
    (new ProvisionStore($store->id))->failed(new RuntimeException('No database server.'));

    expect(array_map(static fn (array $email): array => [$email[0], $email[2], $email[3]], platformAlertEmailsSent()))->toBe([
        [$this->superAdmin->email, 'Setting up the store Ada Shoes failed', "Setting up the store Ada Shoes ({$store->public_id}) failed after every retry. Retry it from the store list once the cause is fixed."],
        [$this->manager->email, 'La configuration de la boutique Ada Shoes a échoué', "La configuration de la boutique Ada Shoes ({$store->public_id}) a échoué après toutes les tentatives. Relancez-la depuis la liste des boutiques une fois la cause corrigée."],
        [$this->directManager->email, 'Setting up the store Ada Shoes failed', "Setting up the store Ada Shoes ({$store->public_id}) failed after every retry. Retry it from the store list once the cause is fixed."],
    ]);

    // An admin retries the setup the same day, and it fails again: they are told again.
    Tenant::query()->whereKey($store->id)->update(['status' => TenantStatus::Provisioning]);
    (new ProvisionStore($store->id))->failed(new RuntimeException('No database server.'));

    expect(platformAlertEmailsSent())->toHaveCount(6);
});

it('emails a store\'s stock or invoices found out of line by the scheduled checks, with how many, once a day per store', function (): void {
    $store = createStore('alerted-store', ['name' => 'Ada Shoes']);
    createStore('other-alerted-store', ['name' => 'Bo Bags']);
    app()->instance(StoreStockLedger::class, new class implements StoreStockLedger
    {
        public function mismatches(): array
        {
            return [new StockLevelMismatch('variant-1', 'location-1', 9, 5, 0, 0, 0), new StockLevelMismatch('variant-2', 'location-1', 1, 2, 0, 0, 0)];
        }
    });
    app()->instance(StoreInvoiceBalances::class, new class implements StoreInvoiceBalances
    {
        public function mismatches(): array
        {
            return [new InvoiceBalanceMismatch('order-1', 'invoice-1', 'NGN', 5000, 4000)];
        }
    });

    // The checks run twice the same day.
    for ($runs = 0; $runs < 2; $runs++) {
        expect(Artisan::call('inventory:reconcile'))->toBe(1)
            ->and(Artisan::call('orders:reconcile-invoices'))->toBe(1);
    }

    // Stores are checked in no particular order, so the emails are compared as a set.
    $toSuperAdmin = array_column(array_values(array_filter(platformAlertEmailsSent(), fn (array $email): bool => $email[0] === $this->superAdmin->email)), 3, 2);
    expect(array_keys($toSuperAdmin))->toEqualCanonicalizing([
        'Stock levels in Ada Shoes don\'t match their ledger',
        'Stock levels in Bo Bags don\'t match their ledger',
        'Invoices in Ada Shoes don\'t match the sales kept',
        'Invoices in Bo Bags don\'t match the sales kept',
    ])->and([$toSuperAdmin['Stock levels in Ada Shoes don\'t match their ledger'], $toSuperAdmin['Invoices in Ada Shoes don\'t match the sales kept']])->toBe([
        "The scheduled check found stock levels in the store Ada Shoes ({$store->public_id}) that don't match its stock movement ledger (2). The details are in the check's log.",
        "The scheduled check found invoices in the store Ada Shoes ({$store->public_id}) whose credit notes don't add up with the sale money kept or the tax invoiced (1). The details are in the check's log.",
    ])->and(platformAlertEmailsSent())->toHaveCount(2 * 2 * 3);

    $this->travel(1)->days();
    Artisan::call('inventory:reconcile');

    expect(platformAlertEmailsSent())->toHaveCount(2 * 2 * 3 + 2 * 3);
});
