<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\StaffPermissionCatalogue;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Enums\OrderPermission;
use App\Tenant\Orders\Jobs\RenderTaxDocumentPdf;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15b.5: staff with the invoices.view permission see the store's
 * invoices and credit notes and download their PDFs; seeing orders isn't
 * enough. A signed-in customer sees and downloads only their own orders'.
 * A PDF not rendered yet answers 409 tax_document_pdf_not_ready.
 */
uses(TruncatesCentralDatabase::class);

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

it('lets roles grant seeing invoices apart from seeing orders', function (): void {
    expect(app(StaffPermissionCatalogue::class)->all())->toContain(OrderPermission::InvoicesView->value);

    [, $invoice, $creditNote] = invoicedOrder();
    $accountant = $this->store->run(static function (): StaffMember {
        $role = Role::findOrCreate('Accountant', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName(OrderPermission::InvoicesView->value, StaffMember::GUARD));
        $accountant = StaffMember::factory()->create();
        $accountant->assignRole($role);

        return $accountant;
    });

    // Seeing orders isn't enough: invoices carry the buyer's details after the order's are erased.
    foreach (['/invoices', "/invoices/{$invoice->public_id}", "/invoices/{$invoice->public_id}/pdf", '/credit-notes', "/credit-notes/{$creditNote->public_id}", "/credit-notes/{$creditNote->public_id}/pdf"] as $path) {
        staffOrderRequest('GET', $path, as: $this->viewer)->assertForbidden();
        staffOrderRequest('GET', $path, as: $accountant)->assertOk();
    }

    // An accountant sees no orders.
    staffOrderRequest('GET', '/orders', as: $accountant)->assertForbidden();
});

it('shows staff each invoice and credit note as issued, newest first, narrowed by number, order, date or test', function (): void {
    [$first, $firstInvoice, $firstCreditNote] = invoicedOrder();
    $this->travel(1)->days();
    [$second, $secondInvoice] = invoicedOrder();
    $test = $this->store->run(static fn (): Order => markPaid(unpaidOrder(isTest: true)));

    $invoices = staffOrderRequest('GET', '/invoices')->assertOk();
    expect($invoices->json('data.*.number'))->toBe(['TEST-'.$test->number, 'INV-2026-000002', 'INV-2026-000001']);

    $shown = staffOrderRequest('GET', "/invoices/{$firstInvoice->public_id}")->assertOk();
    expect($shown->json('data'))->toMatchArray([
        'id' => $firstInvoice->public_id, 'number' => 'INV-2026-000001', 'is_test' => false, 'order' => ['id' => $first->public_id, 'number' => $first->number],
        'issued_on' => '2026-06-01', 'paid_on' => '2026-06-01', 'currency' => 'NGN', 'pdf_ready' => true,
    ])
        ->and($shown->json('data.buyer'))->toMatchArray(['email' => 'guest@example.com', 'erased_at' => null])
        ->and($shown->json('data.lines.0.kind'))->toBe('item')
        ->and($shown->json('data.totals.total.amount'))->toBe($firstInvoice->total->minorAmount())
        ->and($shown->json('data.credit_notes'))->toHaveCount(1)
        ->and($shown->json('data.credit_notes.0'))->toMatchArray(['id' => $firstCreditNote->public_id, 'number' => 'CN-2026-000001', 'pdf_ready' => true]);

    expect(staffOrderRequest('GET', '/invoices?number=INV-2026-000002')->json('data.*.id'))->toBe([$secondInvoice->public_id])
        ->and(staffOrderRequest('GET', "/invoices?order={$first->public_id}")->json('data.*.id'))->toBe([$firstInvoice->public_id])
        ->and(staffOrderRequest('GET', '/invoices?issued_from=2026-06-02&is_test=0')->json('data.*.id'))->toBe([$secondInvoice->public_id])
        ->and(staffOrderRequest('GET', '/invoices?issued_to=2026-06-01')->json('data.*.id'))->toBe([$firstInvoice->public_id])
        ->and(staffOrderRequest('GET', '/invoices?is_test=1')->json('data.*.number'))->toBe(['TEST-'.$test->number])
        ->and(staffOrderRequest('GET', "/credit-notes?order={$second->public_id}")->json('data.*.number'))->toBe(['CN-2026-000002']);

    $creditNote = staffOrderRequest('GET', "/credit-notes/{$firstCreditNote->public_id}")->assertOk();
    expect($creditNote->json('data'))->toMatchArray([
        'number' => 'CN-2026-000001', 'invoice' => ['id' => $firstInvoice->public_id, 'number' => 'INV-2026-000001'], 'refund' => 'rf_'.$first->number, 'issued_on' => '2026-06-01',
    ])
        ->and($creditNote->json('data.lines.0.kind'))->toBe('adjustment')
        ->and($creditNote->json('data.totals.total.amount'))->toBe(1000);
});

it('downloads a PDF named after its number, never cached, and answers 409 while it is not ready', function (): void {
    [, $invoice, $creditNote] = invoicedOrder();

    $download = staffOrderRequest('GET', "/invoices/{$invoice->public_id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($download->headers->get('Content-Disposition'))->toContain('INV-2026-000001.pdf')
        ->and($download->headers->get('Cache-Control'))->toContain('no-store')
        ->and(substr((string) $download->streamedContent(), 0, 5))->toBe('%PDF-');

    $creditNoteDownload = staffOrderRequest('GET', "/credit-notes/{$creditNote->public_id}/pdf")->assertOk();
    expect($creditNoteDownload->headers->get('Content-Disposition'))->toContain('CN-2026-000001.pdf');

    // Not rendered yet.
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class, RenderTaxDocumentPdf::class]);
    [, $waiting, $waitingCreditNote] = invoicedOrder();

    staffOrderRequest('GET', "/invoices/{$waiting->public_id}/pdf")->assertConflict()->assertJsonPath('code', 'tax_document_pdf_not_ready');
    staffOrderRequest('GET', "/credit-notes/{$waitingCreditNote->public_id}/pdf")->assertConflict()->assertJsonPath('code', 'tax_document_pdf_not_ready');
    expect(staffOrderRequest('GET', "/invoices/{$waiting->public_id}")->json('data.pdf_ready'))->toBeFalse();
});

it('shows a customer only their own orders\' invoices and credit notes', function (): void {
    [$customer, $someoneElse] = $this->store->run(static fn (): array => [
        Customer::factory()->create(['email' => 'ada@example.com', 'email_verified_at' => now()]),
        Customer::factory()->create(['email' => 'eve@example.com', 'email_verified_at' => now()]),
    ]);
    [$mine, $invoice, $creditNote] = invoicedOrder($customer);
    [$theirs, , $theirCreditNote] = invoicedOrder($someoneElse);
    $unpaid = $this->store->run(static fn (): Order => placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod(), buyer: OrderBuyer::customer($customer))));

    $as = static fn (string $path, ?Customer $who = null) => customerRequest('GET', $path, as: $who ?? $customer, subdomain: 'orders-store');

    customerRequest('GET', "orders/{$mine->public_id}/invoice", subdomain: 'orders-store')->assertUnauthorized();
    expect($as("orders/{$mine->public_id}/invoice")->assertOk()->json('data'))->toMatchArray(['id' => $invoice->public_id, 'number' => 'INV-2026-000001'])
        ->and($as("orders/{$mine->public_id}/credit-notes/{$creditNote->public_id}")->assertOk()->json('data.number'))->toBe('CN-2026-000001')
        ->and($as("orders/{$mine->public_id}/invoice/pdf")->assertOk()->headers->get('Content-Disposition'))->toContain('INV-2026-000001.pdf')
        ->and($as("orders/{$mine->public_id}/credit-notes/{$creditNote->public_id}/pdf")->assertOk()->headers->get('Content-Disposition'))->toContain('CN-2026-000001.pdf');

    // Someone else's order, an order not paid yet, and a credit note asked for through the wrong order: not found.
    $as("orders/{$theirs->public_id}/invoice")->assertNotFound();
    $as("orders/{$theirs->public_id}/invoice/pdf")->assertNotFound();
    $as("orders/{$theirs->public_id}/credit-notes/{$theirCreditNote->public_id}/pdf")->assertNotFound();
    $as("orders/{$mine->public_id}/credit-notes/{$theirCreditNote->public_id}")->assertNotFound();
    $as("orders/{$mine->public_id}/credit-notes/{$theirCreditNote->public_id}/pdf")->assertNotFound();
    $as("orders/{$unpaid->public_id}/invoice")->assertNotFound();
    expect($as("orders/{$theirs->public_id}/invoice", $someoneElse)->assertOk()->json('data.order.id'))->toBe($theirs->public_id);
});
