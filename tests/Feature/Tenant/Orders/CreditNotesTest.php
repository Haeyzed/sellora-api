<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Shared\Privacy\PersonalDataErasure;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Orders\Actions\IssueCreditNote;
use App\Tenant\Orders\Data\CreditRequest;
use App\Tenant\Orders\Exceptions\CreditNoteExceedsInvoiceException;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\CreditNoteLine;
use App\Tenant\Orders\Models\CreditNoteTax;
use App\Tenant\Orders\Models\DocumentSequence;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\InvoiceTax;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\RefundPurpose;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Jobs\IssueRefundCreditNote;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Services\RefundCredits;
use App\Tenant\Pricing\Actions\AssignTaxClass;
use App\Tenant\Pricing\Models\TaxClass;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15b.3: a credit note for each part of a refund that reduced the sale
 * (returned units, shipping, or a price reduction), once that part has gone
 * back; none for money given back that was never part of the sale. Credit
 * notes copy their invoice's seller and buyer, credit its lines with their
 * own taxes, spread a price reduction's tax across the invoice's rates by
 * largest remainder, and never add up to more than the invoice.
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

it('credits returned units and shipping against their invoice lines, with their own taxes', function (): void {
    $variant = $this->store->run(static function (): ProductVariant {
        addTaxRate('Standard', 'NG', null, '7.5');

        return cartVariant(1000, stock: 5);
    });
    [$order] = gatewayPaidOrder([[$variant, 3]]);
    $line = $this->store->run(static fn (): string => $order->lines()->sole()->public_id);

    refundOrder($order, lines: [$line => 1]);
    refundOrder($order, lines: [$line => 2], shipping: true);

    [$invoice, $notes, $events] = $this->store->run(static fn (): array => [
        Invoice::query()->with('lines')->sole(), CreditNote::query()->with('lines.taxes')->orderBy('id')->get(), $order->events()->pluck('type')->map->value->all(),
    ]);
    $describe = static fn (CreditNoteLine $line): array => [$line->kind->value, $line->invoice_line_id, $line->quantity, $line->total->minorAmount(), $line->tax->minorAmount(), $line->taxes->map(static fn (CreditNoteTax $tax): array => [$tax->name, $tax->rate_percent->toString(), $tax->tax->minorAmount()])->all()];
    $shipping = $invoice->lines[1];

    // ₦30 including 7.5% VAT is ₦2.09 of tax: 0.70 for the first unit, 1.39 for the other two.
    expect($notes->map->only(['number', 'series', 'sequence_number', 'is_test', 'seller_name', 'buyer_email'])->all())->toBe([
        ['number' => 'CN-2026-000001', 'series' => '2026', 'sequence_number' => 1, 'is_test' => false, 'seller_name' => $invoice->seller_name, 'buyer_email' => 'ada@example.com'],
        ['number' => 'CN-2026-000002', 'series' => '2026', 'sequence_number' => 2, 'is_test' => false, 'seller_name' => $invoice->seller_name, 'buyer_email' => 'ada@example.com'],
    ])
        ->and($notes[0]->lines->map($describe)->all())->toBe([['item', $invoice->lines[0]->id, 1, 1000, 70, [['VAT', '7.5000', 70]]]])
        ->and($notes[1]->lines->map($describe)->all())->toBe([
            ['item', $invoice->lines[0]->id, 2, 2000, 139, [['VAT', '7.5000', 139]]],
            ['shipping', $shipping->id, 1, $shipping->total->minorAmount(), $shipping->tax->minorAmount(), [['VAT', '7.5000', $shipping->tax->minorAmount()]]],
        ])
        ->and($notes->map(static fn (CreditNote $note): array => [$note->total->minorAmount(), $note->tax->minorAmount() + $note->net->minorAmount()])->all())->toBe([[1000, 1000], [2000 + $shipping->total->minorAmount(), 2000 + $shipping->total->minorAmount()]])
        // All of it given back: credited exactly to the invoice's total, no further.
        ->and($notes->sum(static fn (CreditNote $note): int => $note->total->minorAmount()))->toBe($invoice->total->minorAmount())
        ->and($events)->toBe(['placed', 'paid', 'invoice_issued', 'payment_status_changed', 'credit_note_issued', 'payment_status_changed', 'credit_note_issued'])
        // Invoices and credit notes are numbered apart.
        ->and($this->store->run(static fn (): array => DocumentSequence::query()->orderBy('kind')->pluck('last_number', 'kind')->all()))->toBe(['credit_note' => 2, 'invoice' => 1]);
});

it('spreads a price reduction\'s tax across the invoice\'s rates by largest remainder', function (): void {
    [$shirt, $book] = $this->store->run(static function (): array {
        addTaxRate('Standard', 'NG', null, '7.5');
        addTaxRate('Reduced', 'NG', null, '2.5', 'Reduced VAT');
        $book = cartVariant(3333, stock: 5, name: 'Book');
        app(AssignTaxClass::class)->toVariant($book, TaxClass::query()->where('name', 'Reduced')->sole());

        return [cartVariant(7777, stock: 5), $book];
    });
    [$order] = gatewayPaidOrder([[$shirt, 1], [$book, 1]]);

    refundOrder($order, 2001);

    [$invoice, $note] = $this->store->run(static fn (): array => [Invoice::query()->with('lines.taxes')->sole(), CreditNote::query()->with('lines.taxes')->sole()]);
    $taxByRate = $invoice->lines->flatMap->taxes->groupBy(static fn (InvoiceTax $tax): string => $tax->name)->map(static fn ($taxes): int => $taxes->sum(static fn (InvoiceTax $tax): int => $tax->tax->minorAmount()))->all();
    // The invoice's share of tax in ₦20.01, then that tax split by what each rate charged; leftover units to the largest remainders.
    $largestRemainder = static function (int $amount, array $weights): array {
        $total = array_sum($weights);
        $parts = array_map(static fn (int $weight): int => intdiv($amount * $weight, $total), $weights);
        $remainders = array_map(static fn (int $weight): int => ($amount * $weight) % $total, $weights);
        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, $amount - array_sum($parts)) as $key) {
            $parts[$key]++;
        }

        return $parts;
    };
    [$tax] = $largestRemainder(2001, [$invoice->tax->minorAmount(), $invoice->total->minorAmount() - $invoice->tax->minorAmount()]);
    $line = $note->lines->sole();

    expect([$line->kind->value, $line->invoice_line_id, $line->name, $line->total->minorAmount(), $line->tax->minorAmount(), $line->net->minorAmount()])->toBe(['adjustment', null, 'Price reduction', 2001, $tax, 2001 - $tax])
        ->and($line->taxes->mapWithKeys(static fn (CreditNoteTax $creditNoteTax): array => [$creditNoteTax->name => $creditNoteTax->tax->minorAmount()])->all())
        ->toBe($largestRemainder($tax, $taxByRate))
        ->and($line->taxes->map(static fn (CreditNoteTax $creditNoteTax): string => $creditNoteTax->rate_percent->toString())->all())->toBe(['7.5000', '2.5000'])
        ->and([$note->tax->minorAmount(), $note->total->minorAmount()])->toBe([$tax, 2001]);
});

it('issues no credit note for money given back that was never part of the sale', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    // ₦130 received of ₦115: the invoice is issued, and ₦15 was paid over.
    recordReceived($payment, 13000);

    $refund = refundOrder($order, 1500, purpose: RefundPurpose::ReturnsPayment);

    expect($this->store->run(static fn (): array => [
        $refund->paymentRefunds()->sole()->status->value, Invoice::query()->count(), CreditNote::query()->count(), DocumentSequence::query()->where('kind', 'credit_note')->count(),
        // Even asked for directly, its part credits nothing.
        app(RefundCredits::class)->requestFor($refund->paymentRefunds()->sole()),
    ]))->toBe(['succeeded', 1, 0, 0, null]);

    // Nor does the sweep find one due.
    $this->travel(11)->minutes();
    Artisan::call('orders:issue-missing-tax-documents');

    expect($this->store->run(static fn (): int => CreditNote::query()->count()))->toBe(0);
});

it('splits a refund drawn on two payments into a credit note for each, in turn, so each line adds up exactly', function (): void {
    [$shirt, $lamp] = $this->store->run(static function (): array {
        addTaxRate('Standard', 'NG', null, '7.5');

        return [cartVariant(1000, stock: 5), cartVariant(1000, stock: 5, name: 'Desk lamp')];
    });
    offerManualMethod(PaymentMethod::BankTransfer);
    [$order, $first] = $this->store->run(static function () use ($shirt, $lamp): array {
        $order = placeOrder(orderDraft([[$shirt, 1], [$lamp, 1]], orderShippingMethod(), paymentDueAt: CarbonImmutable::now()->addHours(72)));

        return [$order, app(App\Tenant\Payments\Actions\StartManualPayment::class)->handle($order, PaymentMethod::BankTransfer)];
    });
    // ₦35 in two payments: ₦17.51, then ₦17.49.
    recordReceived($first, 1751);
    recordReceived($this->store->run(static fn (): Payment => Payment::query()->where('order_id', $order->id)->where('status', 'pending')->sole()), 1749);
    $lines = $this->store->run(static fn () => $order->lines()->get());

    // Both ₦10 lines: ₦17.49 from the newer payment, ₦2.51 from the older.
    $refund = refundOrder($order, lines: [$lines[0]->public_id => 1, $lines[1]->public_id => 1]);

    [$notes, $parts] = $this->store->run(static fn (): array => [CreditNote::query()->with('lines')->orderBy('id')->get(), $refund->paymentRefunds()->get()]);
    $describe = static fn (CreditNote $note): array => $note->lines->map(static fn (CreditNoteLine $line): array => [$line->quantity, $line->total->minorAmount()])->all();

    expect($parts->map(static fn ($part): int => $part->amount->minorAmount())->all())->toBe([1749, 251])
        ->and($notes->map(static fn (CreditNote $note): array => [$note->reference, $note->total->minorAmount()])->all())
        ->toBe($parts->map(static fn ($part): array => [$part->public_id, $part->amount->minorAmount()])->all())
        // ₦17.49 split evenly over the two lines, the leftover kobo to the first; ₦2.51 over what is left of each, so each line comes to exactly ₦10.
        // Its units go with the larger share.
        ->and([$describe($notes[0]), $describe($notes[1])])->toBe([[[1, 875], [1, 874]], [[0, 125], [0, 126]]])
        ->and($notes->sum(static fn (CreditNote $note): int => $note->lines->sum(static fn (CreditNoteLine $line): int => $line->tax->minorAmount())))
        ->toBe($lines->sum(static fn ($line): int => $line->tax->minorAmount()))
        ->and($notes->every(static fn (CreditNote $note): bool => $note->lines->sum(static fn (CreditNoteLine $line): int => $line->total->minorAmount()) === $note->total->minorAmount()))->toBeTrue();
});

it('never credits more than the invoice, in the action or the database', function (): void {
    [$order] = gatewayPaidOrder();
    refundOrder($order, 11000);

    $this->store->run(static function () use ($order): void {
        $note = CreditNote::query()->sole();
        $request = new CreditRequest($order->id, 'rfd_other', 'rf_other', Money::ofMinor(501, 'NGN'), [], Money::zero('NGN'), Money::zero('NGN'), Money::ofMinor(501, 'NGN'));

        expect(static fn () => app(IssueCreditNote::class)->handle($request))->toThrow(CreditNoteExceedsInvoiceException::class)
            ->and(CreditNote::query()->count())->toBe(1);

        $row = collect((array) DB::table('credit_notes')->where('id', $note->id)->first())->except(['id'])->all();

        expect(static fn () => DB::transaction(static fn () => DB::table('credit_notes')->insert([...$row, 'public_id' => strtolower((string) Str::ulid()), 'reference' => 'rfd_x', 'number' => 'CN-2026-000009', 'sequence_number' => 9, 'net_amount' => 501, 'tax_amount' => 0, 'total_amount' => 501])))
            ->toThrow(QueryException::class, 'never add up to more than their invoice');
    });

    // What is left can still be credited.
    refundOrder($order, 500);

    expect($this->store->run(static fn (): array => CreditNote::query()->orderBy('id')->pluck('total_amount')->all()))->toBe([11000, 500]);
});

it('credits a test order\'s invoice with credit notes marked TEST that take no number', function (): void {
    $numbers = $this->store->run(static function (): array {
        $order = markPaid(unpaidOrder(isTest: true));
        $credit = static fn (string $reference): ?CreditNote => app(IssueCreditNote::class)->handle(new CreditRequest($order->id, $reference, 'rf_test', Money::ofMinor(1000, 'NGN'), [], Money::zero('NGN'), Money::zero('NGN'), Money::ofMinor(1000, 'NGN')));

        return [$order->number, $credit('rfd_one')?->number, $credit('rfd_two')?->number, $credit('rfd_one')?->number, DocumentSequence::query()->count()];
    });

    expect($numbers)->toBe([$numbers[0], "TEST-CN-{$numbers[0]}-1", "TEST-CN-{$numbers[0]}-2", "TEST-CN-{$numbers[0]}-1", 0]);
});

it('keeps credit notes as issued: the database refuses changing or deleting them, and lets erasure only clear the buyer, once', function (): void {
    $this->store->run(static fn () => addTaxRate('Standard', 'NG', null, '7.5'));
    [$order] = gatewayPaidOrder();
    refundOrder($order, 1000);

    $this->store->run(static function (): void {
        $note = CreditNote::query()->sole();
        $row = DB::table('credit_notes')->where('id', $note->id);
        $refused = static fn (Closure $change, string $message) => expect(static fn () => DB::transaction($change))->toThrow(QueryException::class, $message);

        $refused(static fn () => (clone $row)->update(['number' => 'CN-2026-000009']), 'never changes once issued');
        $refused(static fn () => (clone $row)->update(['total_amount' => 1, 'net_amount' => 1, 'tax_amount' => 0]), 'never changes once issued');
        $refused(static fn () => (clone $row)->delete(), 'Credit notes are tax documents');
        $refused(static fn () => DB::statement('truncate credit_notes cascade'), 'tax documents');
        $refused(static fn () => DB::table('credit_note_lines')->where('credit_note_id', $note->id)->update(['quantity' => 5]), 'credit_note_lines rows are financial records');
        $refused(static fn () => DB::table('credit_note_taxes')->where('credit_note_id', $note->id)->delete(), 'credit_note_taxes rows are financial records');
        $refused(static fn () => (clone $row)->update(['buyer_email' => null]), 'only through erasure');

        // Stored once it was issued, and never replaced.
        expect($note->pdf_path)->not->toBeNull();
        $refused(static fn () => (clone $row)->update(['pdf_path' => 'credit-notes/b.pdf']), 'stored once');

        $erase = static fn (array $changes) => app(PersonalDataErasure::class)->run(static fn (): int => (clone $row)->update($changes));
        expect(static fn () => $erase(['buyer_email' => null]))->toThrow(QueryException::class, 'only clears');
        $erase(['buyer_name' => null, 'buyer_email' => null, 'buyer_phone' => null, 'billing_address' => null, 'shipping_address' => null, 'buyer_erased_at' => CarbonImmutable::now()]);

        expect(CreditNote::query()->sole()->only(['buyer_email', 'number']))->toBe(['buyer_email' => null, 'number' => $note->number]);
    });
});

it('issues credit notes the queue missed in the hourly sweep, issuing the invoice first if it is missing too', function (): void {
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class, App\Tenant\Orders\Jobs\IssueOrderInvoice::class, IssueRefundCreditNote::class]);
    [$order] = gatewayPaidOrder();
    $refund = refundOrder($order, 2000);

    Queue::assertPushed(IssueRefundCreditNote::class, fn (IssueRefundCreditNote $job): bool => $job->paymentRefundId === $this->store->run(static fn (): int => $refund->paymentRefunds()->sole()->id));

    expect($this->store->run(static fn (): int => Invoice::query()->count() + CreditNote::query()->count()))->toBe(0);

    $this->travel(11)->minutes();
    Artisan::call('orders:issue-missing-tax-documents');

    expect($this->store->run(static fn (): array => [Invoice::query()->sole()->number, CreditNote::query()->sole()->number, CreditNote::query()->sole()->total->minorAmount()]))
        ->toBe(['INV-2026-000001', 'CN-2026-000001', 2000]);
});
