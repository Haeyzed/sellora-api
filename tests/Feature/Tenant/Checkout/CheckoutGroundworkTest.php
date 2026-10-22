<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Orders\Actions\IssueOrderAccessToken;
use App\Tenant\Orders\Data\BuyerTaxId;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Documents\TaxDocumentContent;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAccessToken;
use App\Tenant\Orders\Privacy\GuestDataSubject;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Checkout\FakeGiftCardPaymentMethod;
use Tests\Fixtures\Checkout\MinimumOrderValidator;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
 * Step 16.1: what checkout stands on. Guests may check out and a tax ID is
 * optional in every store until it says otherwise. The buyer's tax ID is
 * copied onto the order and from it onto its invoice and credit notes, and
 * changes only through erasure. Modules' payment methods and validators are
 * used only while the module is enabled. A guest's order token is kept only
 * as a hash and goes with erasure. Checkouts are a person's data, and are
 * purged 30 days after they were last touched.
 */
beforeEach(function (): void {
    seedWorld();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'UTC'));
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('lets the store turn guest checkout off and require a buyer tax ID', function (): void {
    storeSettingsRequest('PATCH', ['guest_checkout' => false, 'buyer_tax_id_requirement' => 'required'], subdomain: 'orders-store')
        ->assertOk()->assertJsonPath('data.guest_checkout', false)->assertJsonPath('data.buyer_tax_id_requirement', 'required');
    storeSettingsRequest('PATCH', ['buyer_tax_id_requirement' => 'sometimes'], subdomain: 'orders-store')->assertJsonValidationErrors('buyer_tax_id_requirement');
});

it('copies the buyer\'s tax ID onto the order, its invoice and its credit notes, and prints it', function (): void {
    [$order, $invoice, $creditNote] = $this->store->run(static function (): array {
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod(), buyer: OrderBuyer::guest('ada@example.com', 'Ada Obi', taxId: new BuyerTaxId('12345678-0001', 'NG')))));
        $creditNote = app(App\Tenant\Orders\Actions\IssueCreditNote::class)->handle(new App\Tenant\Orders\Data\CreditRequest($order->id, 'rfd_1', 'rf_1', App\Shared\Money\Money::ofMinor(1000, 'NGN'), [], App\Shared\Money\Money::zero('NGN'), App\Shared\Money\Money::zero('NGN'), App\Shared\Money\Money::ofMinor(1000, 'NGN')));

        return [$order, App\Tenant\Orders\Models\Invoice::query()->where('order_id', $order->id)->sole(), $creditNote];
    });

    expect([$order->buyer_tax_id, $order->buyer_tax_id_country])->toBe(['12345678-0001', 'NG'])
        ->and([$invoice->buyer_tax_id, $invoice->buyer_tax_id_country])->toBe(['12345678-0001', 'NG'])
        ->and([$creditNote?->buyer_tax_id, $creditNote?->buyer_tax_id_country])->toBe(['12345678-0001', 'NG'])
        ->and($this->store->run(static fn (): array => app(TaxDocumentContent::class)->for($invoice)['buyer']))->toContain('Tax ID (NG): 12345678-0001');
});

it('changes the buyer\'s tax ID only through erasure, on the order and its tax documents', function (): void {
    [$order, $invoice] = $this->store->run(static fn (): array => invoicedOrder());

    $refused = static function (string $table, int $id): void {
        expect(static fn () => test()->store->run(static fn () => DB::table($table)->where('id', $id)->update(['buyer_tax_id' => 'FORGED1', 'buyer_tax_id_country' => 'NG'])))
            ->toThrow(QueryException::class, 'only through erasure');
    };
    $refused('orders', $order->id);
    $refused('invoices', $invoice->id);
});

it('clears the buyer\'s tax ID when their order is anonymised, and keeps a guest\'s order token only until erasure', function (): void {
    [$order, $token] = $this->store->run(static function (): array {
        $order = placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod(), buyer: OrderBuyer::guest('ada@example.com', 'Ada Obi', taxId: new BuyerTaxId('12345678', 'NG'))));

        return [$order, app(IssueOrderAccessToken::class)->handle($order)];
    });

    expect($this->store->run(static fn (): string => OrderAccessToken::query()->sole()->token_hash))->toBe(OrderAccessToken::hash($token))
        ->and(strlen($token))->toBe(64);

    $this->store->run(static fn () => app(PersonalDataRegistry::class)->erase(GuestDataSubject::of('ada@example.com')));

    $erased = $this->store->run(static fn (): Order => Order::query()->findOrFail($order->id));
    expect([$erased->buyer_tax_id, $erased->buyer_tax_id_country])->toBe([null, null])
        ->and($this->store->run(static fn (): int => OrderAccessToken::query()->count()))->toBe(0);
});

it('gives an access token only to a guest\'s order', function (): void {
    $this->store->run(static function (): void {
        $customer = Customer::factory()->create(['email' => 'ada@example.com']);
        $order = placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod(), buyer: OrderBuyer::customer($customer)));

        expect(static fn () => app(IssueOrderAccessToken::class)->handle($order))->toThrow(LogicException::class);
    });
});

it('offers a module\'s payment methods and asks its validators only while the module is enabled, and core\'s always', function (): void {
    $registry = new FeatureRegistry;
    $registry->register(new FeatureDefinition('gift_cards', FeatureKind::Module));
    app()->instance(FeatureRegistry::class, $registry);
    $storeId = (string) $this->store->getTenantKey();

    $extensions = new CheckoutExtensions(app());
    $extensions->addPaymentMethod(FakeGiftCardPaymentMethod::class, 'gift_cards');
    $extensions->addValidator(MinimumOrderValidator::class, 'gift_cards');
    $extensions->addValidator(MinimumOrderValidator::class);

    $counts = fn (): array => $this->store->run(static fn (): array => [count($extensions->paymentMethods()), count($extensions->validators())]);

    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give($storeId, FakeFeatureSource::snapshot(entitled: [])));
    expect($counts())->toBe([0, 1]);

    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give($storeId, FakeFeatureSource::snapshot(entitled: ['gift_cards'])));
    expect($counts())->toBe([1, 2]);

    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give($storeId, FakeFeatureSource::snapshot(entitled: ['gift_cards'], merchantDisabled: ['gift_cards'])));
    expect($counts())->toBe([0, 1]);
});

it('puts a person\'s checkouts in their data export and deletes them on erasure, for customers and guests', function (): void {
    $ada = $this->store->run(static fn (): Customer => Customer::factory()->create(['email' => 'ada@example.com']));
    $this->store->run(static function () use ($ada): void {
        rawCheckout(['customer_id' => $ada->id, 'contact_email' => 'ada@example.com', 'buyer_tax_id' => '12345678', 'buyer_tax_id_country' => 'NG']);
        rawCheckout(['contact_email' => 'grace@example.com', 'marketing_email_consent' => true]);
    });

    $customerExport = $this->store->run(static fn (): array => app(PersonalDataRegistry::class)->export(CustomerDataSubject::of($ada)));
    $guestExport = $this->store->run(static fn (): array => app(PersonalDataRegistry::class)->export(GuestDataSubject::of('grace@example.com')));

    expect($customerExport['checkouts'][0]['tax_id'])->toBe(['id' => '12345678', 'country' => 'NG'])
        ->and(array_column($customerExport['checkouts'], 'contact'))->toBe([['email' => 'ada@example.com', 'name' => null, 'phone' => null]])
        ->and($guestExport['checkouts'][0]['marketing_email_consent'])->toBeTrue();

    $this->store->run(static fn () => app(PersonalDataRegistry::class)->erase(GuestDataSubject::of('grace@example.com')));
    expect($this->store->run(static fn (): array => Checkout::query()->pluck('contact_email')->all()))->toBe(['ada@example.com']);
});

it('purges checkouts nobody has touched for 30 days, though their carts stay', function (): void {
    // The carts are fresh, so only the checkouts' own retention removes the old one.
    $this->store->run(static function (): void {
        rawCheckout(['contact_email' => 'old@example.com', 'last_activity_at' => CarbonImmutable::now()->subDays(31)]);
        rawCheckout(['contact_email' => 'recent@example.com', 'last_activity_at' => CarbonImmutable::now()->subDays(29)]);
        PurgeExpiredRecords::dispatchSync();
    });

    expect($this->store->run(static fn (): array => [Checkout::query()->pluck('contact_email')->all(), Cart::query()->count()]))->toBe([['recent@example.com'], 2]);
});

it('purges order tokens a day after they expire', function (): void {
    $this->store->run(static function (): void {
        app(IssueOrderAccessToken::class)->handle(placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod())));
    });

    // 90 days to expire, then a day's retention.
    $this->travel(91)->days();
    $this->store->run(static fn () => PurgeExpiredRecords::dispatchSync());
    expect($this->store->run(static fn (): int => OrderAccessToken::query()->count()))->toBe(1);

    $this->travel(1)->minutes();
    $this->store->run(static fn () => PurgeExpiredRecords::dispatchSync());
    expect($this->store->run(static fn (): int => OrderAccessToken::query()->count()))->toBe(0);
});
