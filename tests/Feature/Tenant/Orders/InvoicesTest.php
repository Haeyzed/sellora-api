<?php

declare(strict_types=1);

use App\Shared\Privacy\PersonalDataErasure;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Actions\IssueInvoice;
use App\Tenant\Orders\Jobs\IssueOrderInvoice;
use App\Tenant\Orders\Models\DocumentSequence;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\InvoiceLine;
use App\Tenant\Orders\Models\InvoiceTax;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Settings\Models\StoreSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15b.2: an order gets its invoice once it is paid in full (cash on
 * delivery: once the cash is recorded), never at placement, so unpaid orders
 * never take a number. At most one per order, issued under the order's lock.
 * The invoice copies everything (seller, buyer, addresses, lines, each tax,
 * totals, currency and rate) and the database keeps it as issued. A test
 * order's invoice is marked TEST and takes no number. The hourly sweep issues
 * any the queue missed.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'UTC'));
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    useFakeGateway();
    connectFakeGateway('live');
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('issues the invoice once the order is paid in full, copying the sale, the seller and the buyer as they were', function (): void {
    $variant = $this->store->run(static function (): ProductVariant {
        addTaxRate('Standard', 'NG', null, '7.5');
        $settings = StoreSettings::query()->sole();
        $settings->setTranslation('invoice_footer', 'en', 'Thank you for shopping with us.');
        $settings->forceFill([
            'legal_name' => 'Ada Fabrics Limited', 'tax_registration_number' => '12345678-0001', 'contact_email' => 'hello@ada.example',
            'address_line1' => '1 Allen Avenue', 'address_city' => 'Ikeja', 'address_state_code' => 'LA', 'address_postal_code' => '100271', 'address_country_code' => 'NG',
        ])->save();

        return cartVariant(10000, stock: 5, name: 'Linen shirt');
    });
    [$order] = gatewayPaidOrder([[$variant, 2]]);

    [$invoice, $order, $storeName, $events] = $this->store->run(static fn (): array => [
        Invoice::query()->with('lines.taxes')->sole(), $order->refresh()->load(['lines', 'shippingLine', 'taxes']), StoreSettings::query()->sole()->name,
        $order->events()->pluck('type')->map->value->all(),
    ]);

    expect($invoice->only(['number', 'series', 'sequence_number', 'is_test', 'locale', 'currency', 'base_currency', 'tax_country_code']))
        ->toBe(['number' => 'INV-2026-000001', 'series' => '2026', 'sequence_number' => 1, 'is_test' => false, 'locale' => 'en', 'currency' => 'NGN', 'base_currency' => 'NGN', 'tax_country_code' => 'NG'])
        ->and([$invoice->order_id, $invoice->tax_mode, $invoice->issued_at->toIso8601String(), $invoice->paid_at->toIso8601String()])->toBe([$order->id, $order->tax_mode, '2026-06-01T10:00:00+00:00', $order->paid_at?->toIso8601String()])
        ->and($invoice->exchange_rate->isEqualTo($order->exchange_rate))->toBeTrue()
        // The seller as the store's settings said when it was issued.
        ->and($invoice->only(['seller_name', 'seller_trading_name', 'seller_tax_registration_label', 'seller_tax_registration_number', 'seller_email', 'footer']))
        ->toBe(['seller_name' => 'Ada Fabrics Limited', 'seller_trading_name' => $storeName, 'seller_tax_registration_label' => 'TIN', 'seller_tax_registration_number' => '12345678-0001', 'seller_email' => 'hello@ada.example', 'footer' => 'Thank you for shopping with us.'])
        // Addresses are stored as JSON, which keeps no key order.
        ->and($invoice->seller_address)->toEqual(['line1' => '1 Allen Avenue', 'line2' => null, 'city' => 'Ikeja', 'state_code' => 'LA', 'postal_code' => '100271', 'country_code' => 'NG'])
        // The buyer, as the order holds them.
        ->and($invoice->only(['buyer_name', 'buyer_email', 'buyer_phone', 'billing_address']))->toBe(['buyer_name' => 'Ada Obi', 'buyer_email' => 'ada@example.com', 'buyer_phone' => '+2348012345678', 'billing_address' => null])
        ->and($invoice->shipping_address)->toEqual(['recipient_name' => 'Ada Obi', 'company' => null, 'line1' => '5 Allen Avenue', 'line2' => null, 'city' => 'Ikeja', 'state_code' => 'LA', 'postal_code' => '100271', 'country_code' => 'NG', 'phone' => '+2348012345678'])
        // The totals are the order's.
        ->and(array_map(static fn ($money): int => $money->minorAmount(), [$invoice->subtotal, $invoice->discount, $invoice->shipping, $invoice->shipping_discount, $invoice->tax, $invoice->total, $invoice->base_total]))
        ->toBe(array_map(static fn ($money): int => $money->minorAmount(), [$order->subtotal, $order->discount, $order->shipping, $order->shipping_discount, $order->tax, $order->total, $order->base_total]))
        ->and($events)->toBe(['placed', 'paid', 'invoice_issued']);

    // Each line as charged, then the shipping, each with its taxes, which add up to the order's.
    /** @var OrderLine $shirt */
    $shirt = $order->lines->sole();
    $amounts = static fn (InvoiceLine|OrderLine $line): array => array_map(static fn ($money): int => $money->minorAmount(), [$line->subtotal, $line->discount, $line->net, $line->tax, $line->total]);

    expect($invoice->lines->map(static fn (InvoiceLine $line): array => [$line->position, $line->kind->value, $line->name, $line->quantity, $line->unit_price->minorAmount()])->all())
        ->toBe([[0, 'item', 'Linen shirt', 2, 10000], [1, 'shipping', 'Standard', 1, 1500]])
        ->and($invoice->lines[0]->order_line_id)->toBe($shirt->id)
        ->and($amounts($invoice->lines[0]))->toBe($amounts($shirt))
        ->and([$invoice->lines[1]->net->minorAmount(), $invoice->lines[1]->tax->minorAmount(), $invoice->lines[1]->total->minorAmount()])
        ->toBe([$order->shippingLine?->net->minorAmount(), $order->shippingLine?->tax->minorAmount(), 1500])
        ->and($invoice->lines->map(static fn (InvoiceLine $line): array => $line->taxes->map(static fn (InvoiceTax $tax): array => [$tax->name, $tax->rate_percent->toString(), $tax->tax->minorAmount()])->all())->all())
        ->toBe([[['VAT', '7.5000', $shirt->tax->minorAmount()]], [['VAT', '7.5000', $order->shippingLine?->tax->minorAmount()]]])
        ->and($invoice->lines->sum(static fn (InvoiceLine $line): int => $line->taxes->sum(static fn (InvoiceTax $tax): int => $tax->tax->minorAmount())))->toBe($order->tax->minorAmount());

    // Changing the store's settings later never changes it.
    $this->store->run(static fn () => StoreSettings::query()->sole()->update(['legal_name' => 'Ada Holdings', 'tax_registration_number' => '999']));

    expect($this->store->run(static fn (): array => Invoice::query()->sole()->only(['seller_name', 'seller_tax_registration_number'])))
        ->toBe(['seller_name' => 'Ada Fabrics Limited', 'seller_tax_registration_number' => '12345678-0001']);
});

it('issues no invoice for an order never sold, and only one for an order paid in full', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);

    // Unpaid, then paid only in part: no invoice, no number taken.
    expect($this->store->run(static fn (): ?Invoice => app(IssueInvoice::class)->handle($order)))->toBeNull();
    recordReceived($payment, 4000);
    expect($this->store->run(static fn (): int => Invoice::query()->count() + DocumentSequence::query()->count()))->toBe(0);

    // Paid in full: one invoice, however often it is asked for.
    recordReceived($this->store->run(static fn (): Payment => Payment::query()->where('order_id', $order->id)->where('status', 'pending')->sole()), 7500);
    $again = $this->store->run(static fn (): ?Invoice => app(IssueInvoice::class)->handle($order));

    expect($this->store->run(static fn (): array => [Invoice::query()->sole()->id, Invoice::query()->sole()->number, DocumentSequence::query()->sole()->last_number]))
        ->toBe([$again?->id, 'INV-2026-000001', 1]);

    // A payment that arrives after the order was cancelled never makes it a sale.
    $cancelled = $this->store->run(function (): Order {
        $order = unpaidOrder();
        app(CancelOrder::class)->byStaff($this->owner, $order);

        return markPaid($order);
    });

    expect($cancelled->refund_due)->toBeTrue()
        ->and($this->store->run(static fn (): ?Invoice => app(IssueInvoice::class)->handle($cancelled)))->toBeNull()
        ->and($this->store->run(static fn (): array => [Invoice::query()->count(), DocumentSequence::query()->sole()->last_number]))->toBe([1, 1]);
});

it('issues cash on delivery\'s invoice only once the cash is collected, though it was delivered before', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);
    $this->store->run(static fn (): Shipment => shipOrder($order, deliver: true));

    expect($this->store->run(static fn (): int => Invoice::query()->count()))->toBe(0);

    recordReceived($payment, 11500);

    expect($this->store->run(static fn (): string => Invoice::query()->sole()->number))->toBe('INV-2026-000001');
});

it('marks a test order\'s invoice TEST, and never lets it take a number', function (): void {
    [$test, $live] = $this->store->run(static fn (): array => [markPaid(unpaidOrder(isTest: true)), markPaid(unpaidOrder())]);

    expect($this->store->run(static fn (): array => Invoice::query()->orderBy('id')->get()->map->only(['order_id', 'number', 'series', 'sequence_number', 'is_test'])->all()))->toBe([
        ['order_id' => $test->id, 'number' => 'TEST-'.$test->number, 'series' => null, 'sequence_number' => null, 'is_test' => true],
        ['order_id' => $live->id, 'number' => 'INV-2026-000001', 'series' => '2026', 'sequence_number' => 1, 'is_test' => false],
    ])->and($this->store->run(static fn (): int => DocumentSequence::query()->sole()->last_number))->toBe(1);
});

it('issues the invoices the queue missed in the hourly sweep, once they have been due a while', function (): void {
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class, IssueOrderInvoice::class]);
    [$order] = gatewayPaidOrder();
    // Never sold: nothing to issue.
    $this->store->run(static fn (): Order => unpaidOrder());

    Queue::assertPushed(IssueOrderInvoice::class, static fn (IssueOrderInvoice $job): bool => $job->orderId === $order->id && $job->queue === null);

    // The queue may still be about to issue it.
    expect(Artisan::call('orders:issue-missing-tax-documents'))->toBe(0)
        ->and($this->store->run(static fn (): int => Invoice::query()->count()))->toBe(0);

    $this->travel(11)->minutes();

    expect(Artisan::call('orders:issue-missing-tax-documents'))->toBe(0)
        ->and(Artisan::output())->toContain('1 missing tax documents issued')
        ->and($this->store->run(static fn (): array => Invoice::query()->pluck('order_id')->all()))->toBe([$order->id]);

    Artisan::call('orders:issue-missing-tax-documents');

    expect($this->store->run(static fn (): int => Invoice::query()->count()))->toBe(1);
});

it('waits for the order\'s lock, so an order never gets two invoices', function (): void {
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class, IssueOrderInvoice::class]);
    [$order] = gatewayPaidOrder();

    $this->store->run(static function () use ($order): void {
        $other = SecondConnection::open()->holdRowLock($order);

        expect($other->blockedStatement(static fn (): ?Invoice => app(IssueInvoice::class)->handle($order)))->toContain('"orders"')->toContain('for update');

        // The other request issues it and commits.
        $other->run(static fn (Connection $db): bool => $db->table('invoices')->insert(invoiceRow($order, ['number' => 'TEST-other', 'is_test' => true])));
        $other->commit();

        expect(app(IssueInvoice::class)->handle($order)?->number)->toBe('TEST-other')
            ->and(Invoice::query()->count())->toBe(1)
            ->and(DocumentSequence::query()->count())->toBe(0);
    });
});

it('keeps invoices as issued: the database refuses changing or deleting them, and lets erasure only clear the buyer, once', function (): void {
    $this->store->run(static fn () => addTaxRate('Standard', 'NG', null, '7.5'));
    gatewayPaidOrder();

    $this->store->run(static function (): void {
        $invoice = Invoice::query()->sole();
        $row = DB::table('invoices')->where('id', $invoice->id);
        $refused = static fn (Closure $change, string $message) => expect(static fn () => DB::transaction($change))->toThrow(QueryException::class, $message);

        $refused(static fn () => (clone $row)->update(['number' => 'INV-2026-000009']), 'never changes once issued');
        $refused(static fn () => (clone $row)->update(['total_amount' => 1]), 'never changes once issued');
        $refused(static fn () => (clone $row)->update(['seller_name' => 'Someone else']), 'never changes once issued');
        $refused(static fn () => (clone $row)->delete(), 'Invoices are tax documents');
        $refused(static fn () => DB::statement('truncate invoices cascade'), 'tax documents');
        $refused(static fn () => DB::table('invoice_lines')->where('invoice_id', $invoice->id)->update(['quantity' => 5]), 'invoice_lines rows are financial records');
        $refused(static fn () => DB::table('invoice_taxes')->where('invoice_id', $invoice->id)->delete(), 'invoice_taxes rows are financial records');
        // Its buyer changes only through erasure.
        $refused(static fn () => (clone $row)->update(['buyer_name' => null]), 'only through erasure');
        // A live invoice always has a number of its series; a test one never does.
        $refused(static fn () => DB::table('invoices')->insert(invoiceRow(unpaidOrder(), ['number' => 'TEST-1', 'is_test' => false, 'series' => '2026', 'sequence_number' => 2])), 'invoices_test_take_no_number');

        // Its PDF is stored once.
        (clone $row)->update(['pdf_path' => 'invoices/a.pdf', 'pdf_rendered_at' => CarbonImmutable::now()]);
        $refused(static fn () => (clone $row)->update(['pdf_path' => 'invoices/b.pdf']), 'stored once');
        $refused(static fn () => DB::table('invoices')->insert(invoiceRow(unpaidOrder(), ['pdf_path' => 'invoices/c.pdf'])), 'invoices_pdf_complete');

        // Erasure clears the buyer, only to nothing, once.
        $erase = static fn (array $changes) => app(PersonalDataErasure::class)->run(static fn (): int => (clone $row)->update($changes));
        expect(static fn () => $erase(['buyer_name' => 'Someone else', 'buyer_erased_at' => CarbonImmutable::now()]))->toThrow(QueryException::class, 'invoices_buyer_erased')
            ->and(static fn () => $erase(['buyer_name' => null]))->toThrow(QueryException::class, 'only clears');

        $erase(['buyer_name' => null, 'buyer_email' => null, 'buyer_phone' => null, 'billing_address' => null, 'shipping_address' => null, 'buyer_erased_at' => CarbonImmutable::now()]);

        expect(Invoice::query()->sole()->only(['buyer_name', 'buyer_email', 'shipping_address', 'number']))->toBe(['buyer_name' => null, 'buyer_email' => null, 'shipping_address' => null, 'number' => $invoice->number])
            ->and(static fn () => $erase(['buyer_erased_at' => CarbonImmutable::now()->addDay()]))->toThrow(QueryException::class, 'only clears');
    });
});
