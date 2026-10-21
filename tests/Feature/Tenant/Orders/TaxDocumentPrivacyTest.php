<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Customers\Actions\EraseCustomer;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Orders\Actions\IssueCreditNote;
use App\Tenant\Orders\Data\CreditRequest;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Privacy\GuestDataSubject;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Settings\Models\StoreSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15b.6: invoices and credit notes are tax records. Erasing their buyer
 * keeps the buyer's details on them, as issued, until the store country's
 * tax-record retention ends (counted from the end of the year each was
 * issued in), and says until when; after that, the daily purge clears them
 * from documents whose order was anonymised, and an erasure clears them at
 * once. They are in the buyer's data export.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'UTC'));
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
    $this->customer = $this->store->run(static fn (): Customer => Customer::factory()->create(['email' => 'ada@example.com', 'password' => 'a-long-shopper-password', 'email_verified_at' => now()]));
});

afterEach(function (): void {
    deleteAllStores();
});

it('keeps a buyer\'s details on tax documents through erasure, says until when, and the purge clears them once retention ends', function (): void {
    [$order] = invoicedOrder($this->customer);
    // Delivered, so erasure anonymises the order at once.
    deliverOrder($order);

    $erased = customerRequest('DELETE', 'account', ['current_password' => 'a-long-shopper-password'], $this->customer, 'orders-store')->assertOk();

    // Nigeria keeps tax records 6 years from the end of the year of issue (2026).
    expect($erased->json('data.tax_documents_kept_until'))->toBe('2032-12-31')
        ->and($erased->json('data.message'))->toContain('December 31, 2032')->toContain('invoices and credit notes')
        ->and($this->store->run(static fn (): ?string => Order::query()->sole()->anonymised_at?->toDateString()))->toBe('2026-06-01')
        ->and(buyerEmailsOnTaxDocuments())->toBe(['INV-2026-000001' => 'ada@example.com', 'CN-2026-000001' => 'ada@example.com']);

    // The last day it must be kept: still there.
    $this->travelTo(CarbonImmutable::parse('2032-12-31 12:00', 'Africa/Lagos'));
    $this->store->run(static fn () => PurgeExpiredRecords::dispatchSync());
    expect(buyerEmailsOnTaxDocuments())->toBe(['INV-2026-000001' => 'ada@example.com', 'CN-2026-000001' => 'ada@example.com']);

    // The next day, in the store's timezone: cleared, the rest of each document kept.
    $this->travelTo(CarbonImmutable::parse('2033-01-01 00:30', 'Africa/Lagos'));
    $this->store->run(static fn () => PurgeExpiredRecords::dispatchSync());

    expect(buyerEmailsOnTaxDocuments())->toBe(['INV-2026-000001' => null, 'CN-2026-000001' => null])
        ->and($this->store->run(static fn (): array => [Invoice::query()->sole()->only(['buyer_name', 'buyer_phone', 'billing_address', 'shipping_address']), Invoice::query()->sole()->buyer_erased_at !== null, Invoice::query()->sole()->total->minorAmount(), CreditNote::query()->sole()->buyer_erased_at !== null]))
        ->toBe([['buyer_name' => null, 'buyer_phone' => null, 'billing_address' => null, 'shipping_address' => null], true, 11500, true]);
});

it('clears nothing in the purge while the order still holds its buyer\'s details', function (): void {
    invoicedOrder($this->customer);

    // Past retention, but the order was never erased nor anonymised: the documents match it.
    $this->travelTo(CarbonImmutable::parse('2034-06-01 10:00', 'UTC'));
    $this->store->run(static fn () => PurgeExpiredRecords::dispatchSync());

    expect(buyerEmailsOnTaxDocuments())->toBe(['INV-2026-000001' => 'ada@example.com', 'CN-2026-000001' => 'ada@example.com']);
});

it('clears tax documents already past retention at once on erasure, and keeps the latest still within it', function (): void {
    invoicedOrder($this->customer);
    $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00', 'UTC'));
    invoicedOrder($this->customer);

    // Both within retention: kept until the later one's ends.
    expect($this->store->run(fn () => app(PersonalDataRegistry::class)->keptByLawUntil(CustomerDataSubject::of($this->customer))?->toDateString()))->toBe('2033-12-31');

    // In 2033 the 2026 documents are past retention; the 2027 ones are kept until the end of 2033.
    $this->travelTo(CarbonImmutable::parse('2033-02-01 10:00', 'UTC'));
    $outcome = $this->store->run(fn () => app(EraseCustomer::class)->erase($this->customer));

    expect($outcome->keptByLawUntil?->toDateString())->toBe('2033-12-31')
        ->and(buyerEmailsOnTaxDocuments())->toBe([
            'INV-2026-000001' => null, 'INV-2027-000001' => 'ada@example.com',
            'CN-2026-000001' => null, 'CN-2027-000001' => 'ada@example.com',
        ]);

    // Nothing left to keep after the end of 2033: a later erasure says so.
    $this->travelTo(CarbonImmutable::parse('2034-01-02 10:00', 'UTC'));
    expect($this->store->run(fn () => app(EraseCustomer::class)->erase($this->customer))->keptByLawUntil)->toBeNull()
        ->and(buyerEmailsOnTaxDocuments())->toBe(['INV-2026-000001' => null, 'INV-2027-000001' => null, 'CN-2026-000001' => null, 'CN-2027-000001' => null]);
});

it('keeps the details until the latest document\'s retention ends, a later credit note\'s included', function (): void {
    [$order] = invoicedOrder($this->customer);
    // Another refund, two years later, credits the 2026 invoice in 2028.
    $this->travelTo(CarbonImmutable::parse('2028-05-01 10:00', 'UTC'));
    $this->store->run(static fn () => app(IssueCreditNote::class)->handle(new CreditRequest($order->id, 'rfd_later', 'rf_later', Money::ofMinor(500, 'NGN'), [], Money::zero('NGN'), Money::zero('NGN'), Money::ofMinor(500, 'NGN'))));

    $outcome = $this->store->run(fn () => app(EraseCustomer::class)->erase($this->customer));

    expect($outcome->keptByLawUntil?->toDateString())->toBe('2034-12-31');
});

it('says nothing is kept when the buyer has no tax documents', function (): void {
    $erased = customerRequest('DELETE', 'account', ['current_password' => 'a-long-shopper-password'], $this->customer, 'orders-store')->assertOk();

    expect($erased->json('data'))->toBe(['tax_documents_kept_until' => null, 'message' => __('privacy.erased')]);
});

it('puts a buyer\'s invoices and credit notes in their data export, as issued', function (): void {
    invoicedOrder($this->customer);
    invoicedOrder();

    $export = $this->store->run(fn (): array => app(PersonalDataRegistry::class)->export(CustomerDataSubject::of($this->customer)));

    expect($export['tax_documents'])->toHaveCount(2)
        ->and($export['tax_documents'][0])->toMatchArray(['type' => 'invoice', 'number' => 'INV-2026-000001', 'issued_on' => '2026-06-01'])
        ->and($export['tax_documents'][0]['buyer']['email'])->toBe('ada@example.com')
        ->and($export['tax_documents'][0]['buyer']['shipping_address'])->not->toBeNull()
        ->and($export['tax_documents'][1])->toMatchArray(['type' => 'credit_note', 'number' => 'CN-2026-000001', 'invoice' => 'INV-2026-000001']);
});

it('keeps a guest\'s tax documents through their erasure, found by the email they were issued to', function (): void {
    [$order] = invoicedOrder();
    deliverOrder($order);
    invoicedOrder($this->customer);

    $erased = staffOrderRequest('POST', '/guest-personal-data/erasure', ['email' => 'Guest@Example.com', 'password' => 'Password@123'])->assertOk();

    expect($erased->json('data.tax_documents_kept_until'))->toBe('2032-12-31')
        ->and($this->store->run(static fn (): ?string => Order::query()->where('id', $order->id)->sole()->contact_email))->toBeNull()
        // Still found through the documents, though the anonymised order no longer holds the email.
        ->and(staffOrderRequest('POST', '/guest-personal-data', ['email' => 'guest@example.com'])->json('data.tax_documents.*.number'))->toBe(['INV-2026-000001', 'CN-2026-000001'])
        // The customer's own documents are not the guest's.
        ->and($this->store->run(static fn (): array => app(PersonalDataRegistry::class)->export(GuestDataSubject::of('ada@example.com'))['tax_documents']))->toBe([]);
});

it('takes the tax-record retention from the store\'s country, ten years where it isn\'t known', function (): void {
    $years = static fn (string $country): int => (new StoreSettings)->forceFill(['country_code' => $country])->taxRecordRetentionYears();

    expect(array_map($years, ['DE', 'GB', 'FR', 'KE', 'ZA', 'NG', 'US', 'BR']))->toBe([8, 6, 10, 5, 5, 6, 7, 10])
        ->and(staffOrderRequest('GET', '/store/settings')->json('data.tax_record_retention_years'))->toBe(6);
});
