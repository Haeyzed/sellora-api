<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Money\Money;
use App\Tenant\Orders\Data\CreditedLine;
use App\Tenant\Orders\Data\CreditRequest;
use App\Tenant\Orders\Enums\CreditNoteLineKind;
use App\Tenant\Orders\Enums\InvoiceLineKind;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Events\CreditNoteIssued;
use App\Tenant\Orders\Exceptions\CreditNoteExceedsInvoiceException;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\CreditNoteLine;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\InvoiceLine;
use App\Tenant\Orders\Models\InvoiceTax;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Orders\Services\OrderProgress;
use App\Tenant\Orders\Services\TaxDocumentNumbers;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Issues the credit note for one part of a refund that reduced the sale, once that part has gone back. Safe to run twice: one credit note per part. Also credits a whole invoice when a sale is undone without a refund (undoSale()).
 *
 * Runs under the order's lock, issuing the invoice first if the queue hasn't
 * yet. Returned units and shipping are credited against their invoice lines,
 * their tax split across each line's own taxes. A price reduction is an
 * adjustment line: its tax is the invoice's share of tax in the amount, split
 * across the invoice's rates by largest remainder. Credit notes never add up
 * to more than the invoice. A test order's credit notes are numbered
 * "TEST-CN-", the order number and a count, and take no number.
 */
final readonly class IssueCreditNote
{
    public function __construct(
        private OrderProgress $orderProgress,
        private IssueInvoice $issueInvoice,
        private TaxDocumentNumbers $taxDocumentNumbers,
        private OrderEventLog $orderEventLog,
        private FindStoreSettings $findStoreSettings,
    ) {}

    /**
     * @return CreditNote|null The part's credit note; null when its order was never sold, so it has no invoice to credit.
     *
     * @throws CreditNoteExceedsInvoiceException When it would credit more than is left on the invoice.
     */
    public function handle(CreditRequest $request): ?CreditNote
    {
        return DB::transaction(function () use ($request): ?CreditNote {
            $locked = $this->orderProgress->lock(Order::query()->findOrFail($request->orderId));
            $existing = CreditNote::query()->where('reference', $request->reference)->first();

            if ($existing !== null) {
                return $existing;
            }

            $invoice = $this->issueInvoice->handle($locked)?->load('lines.taxes');

            if ($invoice === null) {
                return null;
            }

            $credited = (int) CreditNote::query()->where('invoice_id', $invoice->id)->sum('total_amount');

            if ($credited + $request->amount->minorAmount() > $invoice->total->minorAmount()) {
                throw CreditNoteExceedsInvoiceException::for($invoice, $request->reference);
            }

            $lines = $this->linesFor($request, $invoice);
            $creditNote = $this->copyOf($invoice, $locked, $request, $lines, CarbonImmutable::now());
            $creditNote->save();
            $this->saveLines($creditNote, $lines);
            $this->orderEventLog->record($locked, OrderEventType::CreditNoteIssued, ['credit_note' => $creditNote->public_id, 'number' => $creditNote->number, 'refund' => $request->refundReference]);
            CreditNoteIssued::dispatch($creditNote);

            return $creditNote;
        });
    }

    /**
     * Credits what is left of a sold order's invoice once the sale is undone without a refund: its payment status dropped from paid to unpaid after it was cancelled, because what paid for it was only held and has been released. Runs in the caller's transaction, under the order's lock. Safe to run twice: one such credit note per order.
     *
     * Credits every line and the shipping as invoiced, with their taxes, when
     * nothing was credited yet; otherwise what is left, as a price reduction.
     *
     * @return CreditNote|null The credit note; null when the order has no invoice (it was never issued), or nothing is left on it.
     */
    public function undoSale(Order $order): ?CreditNote
    {
        return DB::transaction(function () use ($order): ?CreditNote {
            $locked = $this->orderProgress->lock($order);
            $existing = CreditNote::query()->where('reference', $locked->public_id)->first();

            if ($existing !== null) {
                return $existing;
            }

            // Never issued now: the order is no longer a sale.
            $invoice = Invoice::query()->where('order_id', $locked->id)->first()?->load('lines.taxes');

            if ($invoice === null) {
                return null;
            }

            $credited = (int) CreditNote::query()->where('invoice_id', $invoice->id)->sum('total_amount');
            $left = $invoice->total->subtract(Money::ofMinor($credited, $invoice->currency));

            if (! $left->isPositive()) {
                return null;
            }

            $request = new CreditRequest($locked->id, $locked->public_id, null, $left, [], Money::zero($invoice->currency), Money::zero($invoice->currency), Money::zero($invoice->currency));
            $lines = $credited === 0 ? $this->wholeInvoice($invoice) : [['kind' => CreditNoteLineKind::Adjustment, 'invoiceLine' => null, 'name' => __('documents.price_reduction', locale: $invoice->locale), 'quantity' => 1, 'total' => $left, 'taxes' => $this->adjustmentTaxes($left, $invoice)]];
            $creditNote = $this->copyOf($invoice, $locked, $request, $lines, CarbonImmutable::now());
            $creditNote->save();
            $this->saveLines($creditNote, $lines);
            $this->orderEventLog->record($locked, OrderEventType::CreditNoteIssued, ['credit_note' => $creditNote->public_id, 'number' => $creditNote->number, 'refund' => null]);
            CreditNoteIssued::dispatch($creditNote);

            return $creditNote;
        });
    }

    /**
     * Every line of the invoice as it was invoiced, shipping included, each with all its taxes.
     *
     * @return list<array{kind: CreditNoteLineKind, invoiceLine: InvoiceLine|null, name: string, quantity: int, total: Money, taxes: list<array{InvoiceTax, Money}>}>
     */
    private function wholeInvoice(Invoice $invoice): array
    {
        $lines = [];

        foreach ($invoice->lines->sortBy('position') as $line) {
            $taxes = [];

            foreach ($line->taxes as $invoiceTax) {
                $taxes[] = [$invoiceTax, $invoiceTax->tax];
            }

            $lines[] = [
                'kind' => $line->kind === InvoiceLineKind::Shipping ? CreditNoteLineKind::Shipping : CreditNoteLineKind::Item,
                'invoiceLine' => $line,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'total' => $line->total,
                'taxes' => $taxes,
            ];
        }

        $total = Money::sum($invoice->currency, array_map(static fn (array $line): Money => $line['total'], $lines));

        if (! $total->isEqualTo($invoice->total)) {
            throw new LogicException('An invoice\'s lines must add up to its total.');
        }

        return $lines;
    }

    /**
     * The credit note's lines, in order: returned units, shipping, then a price reduction.
     *
     * @return list<array{kind: CreditNoteLineKind, invoiceLine: InvoiceLine|null, name: string, quantity: int, total: Money, taxes: list<array{InvoiceTax, Money}>}>
     */
    private function linesFor(CreditRequest $request, Invoice $invoice): array
    {
        $lines = [];

        foreach ($request->lines as $credited) {
            $invoiceLine = $this->invoiceLineFor($invoice, $credited);
            $lines[] = ['kind' => CreditNoteLineKind::Item, 'invoiceLine' => $invoiceLine, 'name' => $invoiceLine->name, 'quantity' => $credited->quantity, 'total' => $credited->amount, 'taxes' => $this->spread($credited->tax, $invoiceLine->taxes)];
        }

        if ($request->shipping->isPositive()) {
            $shipping = $invoice->lines->firstWhere('kind', InvoiceLineKind::Shipping) ?? throw new LogicException('Shipping was refunded on an order whose invoice has none.');
            $lines[] = ['kind' => CreditNoteLineKind::Shipping, 'invoiceLine' => $shipping, 'name' => $shipping->name, 'quantity' => 1, 'total' => $request->shipping, 'taxes' => $this->spread($request->shippingTax, $shipping->taxes)];
        }

        if ($request->adjustment->isPositive()) {
            $lines[] = ['kind' => CreditNoteLineKind::Adjustment, 'invoiceLine' => null, 'name' => __('documents.price_reduction', locale: $invoice->locale), 'quantity' => 1, 'total' => $request->adjustment, 'taxes' => $this->adjustmentTaxes($request->adjustment, $invoice)];
        }

        $total = Money::sum($request->amount->currency(), array_map(static fn (array $line): Money => $line['total'], $lines));

        if (! $total->isEqualTo($request->amount)) {
            throw new LogicException('A credit note\'s lines must add up to what its refund part gave back.');
        }

        return $lines;
    }

    private function invoiceLineFor(Invoice $invoice, CreditedLine $credited): InvoiceLine
    {
        return $invoice->lines->firstWhere('order_line_id', $credited->orderLineId)
            ?? throw new LogicException('A refunded line is not on the order\'s invoice.');
    }

    /**
     * Splits a line's credited tax across its own taxes, by what each charged.
     *
     * @param  Collection<int, InvoiceTax>  $taxes
     * @return list<array{InvoiceTax, Money}>
     */
    private function spread(Money $tax, Collection $taxes): array
    {
        $weights = [];

        foreach ($taxes as $key => $invoiceTax) {
            $weights[$key] = $invoiceTax->tax->minorAmount();
        }

        if ($tax->isZero() || $weights === []) {
            return [];
        }

        // A line charged nothing at its rates still credits evenly across them.
        $parts = array_sum($weights) > 0 ? $tax->allocate($weights) : $tax->allocate(array_map(static fn (): int => 1, $weights));
        $spread = [];

        foreach ($taxes as $key => $invoiceTax) {
            $spread[] = [$invoiceTax, $parts[$key]];
        }

        return $spread;
    }

    /**
     * A price reduction's tax: the invoice's share of tax in the amount, split across the invoice's rates by what each charged, by largest remainder.
     *
     * @return list<array{InvoiceTax, Money}>
     */
    private function adjustmentTaxes(Money $amount, Invoice $invoice): array
    {
        $rates = [];
        $weights = [];

        foreach ($invoice->lines as $line) {
            foreach ($line->taxes as $invoiceTax) {
                $key = implode('|', [$invoiceTax->name, $invoiceTax->country_code, $invoiceTax->state_code, $invoiceTax->rate_percent->toString()]);
                $rates[$key] ??= $invoiceTax;
                $weights[$key] = ($weights[$key] ?? 0) + $invoiceTax->tax->minorAmount();
            }
        }

        if ($weights === [] || $invoice->tax->isZero()) {
            return [];
        }

        [$tax] = $amount->allocate([$invoice->tax->minorAmount(), $invoice->total->minorAmount() - $invoice->tax->minorAmount()]);
        $parts = $tax->allocate($weights);
        $taxes = [];

        foreach ($rates as $key => $invoiceTax) {
            $taxes[] = [$invoiceTax, $parts[$key]];
        }

        return $taxes;
    }

    /**
     * @param  list<array{kind: CreditNoteLineKind, invoiceLine: InvoiceLine|null, name: string, quantity: int, total: Money, taxes: list<array{InvoiceTax, Money}>}>  $lines
     */
    private function copyOf(Invoice $invoice, Order $order, CreditRequest $request, array $lines, CarbonImmutable $issuedAt): CreditNote
    {
        $number = $invoice->is_test ? null : $this->taxDocumentNumbers->next(TaxDocumentKind::CreditNote, $issuedAt);
        $tax = Money::sum($invoice->currency, array_merge(...array_map(static fn (array $line): array => array_column($line['taxes'], 1), $lines)));
        $testNumber = CreditNote::TEST_PREFIX.$order->number.'-'.(CreditNote::query()->where('invoice_id', $invoice->id)->count() + 1);

        return (new CreditNote)->forceFill([
            'invoice_id' => $invoice->id,
            'order_id' => $order->id,
            'reference' => $request->reference,
            'refund_reference' => $request->refundReference,
            'number' => $number === null ? $testNumber : $number->number,
            'series' => $number?->series,
            'sequence_number' => $number?->sequenceNumber,
            'is_test' => $invoice->is_test,
            'issued_at' => $issuedAt,
            'issued_on' => $issuedAt->setTimezone($this->findStoreSettings->handle()->timezone)->toDateString(),
            ...$invoice->only([
                'locale', 'currency', 'base_currency', 'exchange_rate', 'tax_mode', 'seller_name', 'seller_trading_name', 'seller_tax_registration_label',
                'seller_tax_registration_number', 'seller_address', 'seller_email', 'seller_phone', 'buyer_name', 'buyer_email', 'buyer_phone',
                'buyer_tax_id', 'buyer_tax_id_country', 'billing_address', 'shipping_address', 'buyer_erased_at', 'footer',
            ]),
            'net' => $request->amount->subtract($tax),
            'tax' => $tax,
            'total' => $request->amount,
        ]);
    }

    /**
     * @param  list<array{kind: CreditNoteLineKind, invoiceLine: InvoiceLine|null, name: string, quantity: int, total: Money, taxes: list<array{InvoiceTax, Money}>}>  $lines
     */
    private function saveLines(CreditNote $creditNote, array $lines): void
    {
        foreach ($lines as $position => $line) {
            $tax = Money::sum($creditNote->currency, array_column($line['taxes'], 1));
            $saved = (new CreditNoteLine)->forceFill([
                'credit_note_id' => $creditNote->id,
                'position' => $position,
                'kind' => $line['kind'],
                'invoice_line_id' => $line['invoiceLine']?->id,
                'name' => $line['name'],
                'variant_name' => $line['invoiceLine']?->variant_name,
                'sku' => $line['invoiceLine']?->sku,
                'quantity' => $line['quantity'],
                'net' => $line['total']->subtract($tax),
                'tax' => $tax,
                'total' => $line['total'],
            ]);
            $saved->save();

            foreach ($line['taxes'] as [$invoiceTax, $part]) {
                $saved->taxes()->forceCreate([
                    'credit_note_id' => $creditNote->id,
                    'name' => $invoiceTax->name,
                    'country_code' => $invoiceTax->country_code,
                    'state_code' => $invoiceTax->state_code,
                    'rate_percent' => $invoiceTax->rate_percent,
                    'tax' => $part,
                ]);
            }
        }
    }
}
