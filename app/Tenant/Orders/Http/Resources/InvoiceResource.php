<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\InvoiceLine;
use App\Tenant\Orders\Models\InvoiceTax;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invoice, exactly as it was issued: seller, buyer, lines with their taxes, totals, and the credit notes against it.
 *
 * Its buyer fields are null once erased, after the store's tax-record
 * retention ended. Its PDF is ready once pdf_ready is true.
 *
 * @property Invoice $resource
 */
final class InvoiceResource extends JsonResource
{
    public function __construct(Invoice $invoice)
    {
        parent::__construct($invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoice = $this->resource;

        return [
            'id' => $invoice->public_id,
            /** As printed, such as "INV-2026-000042"; "TEST-1042" for a test order's, which is not a tax document. */
            'number' => $invoice->number,
            'is_test' => $invoice->is_test,
            'order' => ['id' => $invoice->order->public_id, 'number' => $invoice->order->number],
            /** The issue date printed, in the store's timezone when it was issued (YYYY-MM-DD). */
            'issued_on' => $invoice->issued_on->toDateString(),
            /** The date of the sale printed (YYYY-MM-DD). */
            'paid_on' => $invoice->paid_on->toDateString(),
            'issued_at' => $invoice->issued_at->toIso8601String(),
            'locale' => $invoice->locale,
            'currency' => $invoice->currency,
            /** "inclusive" when prices included tax, or "exclusive". */
            'tax_mode' => $invoice->tax_mode->value,
            ...self::parties($invoice),
            /**
             * @var list<array{kind: string, name: string, variant_name: string|null, sku: string|null, quantity: int, unit_price: MoneyResource, discount: MoneyResource, net: MoneyResource, tax: MoneyResource, total: MoneyResource, taxes: list<array{name: string, rate: string, tax: MoneyResource}>}>
             */
            'lines' => array_values($invoice->lines->map(static fn (InvoiceLine $line): array => [
                /** "item" or "shipping". */
                'kind' => $line->kind->value,
                'name' => $line->name,
                'variant_name' => $line->variant_name,
                'sku' => $line->sku,
                'quantity' => $line->quantity,
                'unit_price' => new MoneyResource($line->unit_price),
                'discount' => new MoneyResource($line->discount),
                'net' => new MoneyResource($line->net),
                'tax' => new MoneyResource($line->tax),
                'total' => new MoneyResource($line->total),
                'taxes' => array_values($line->taxes->map(static fn (InvoiceTax $tax): array => ['name' => $tax->name, 'rate' => $tax->rate_percent->toString(), 'tax' => new MoneyResource($tax->tax)])->all()),
            ])->all()),
            /**
             * @var array{subtotal: MoneyResource, discount: MoneyResource, shipping: MoneyResource, shipping_discount: MoneyResource, tax: MoneyResource, total: MoneyResource}
             */
            'totals' => [
                'subtotal' => new MoneyResource($invoice->subtotal),
                'discount' => new MoneyResource($invoice->discount),
                'shipping' => new MoneyResource($invoice->shipping),
                'shipping_discount' => new MoneyResource($invoice->shipping_discount),
                'tax' => new MoneyResource($invoice->tax),
                'total' => new MoneyResource($invoice->total),
            ],
            'footer' => $invoice->footer,
            /**
             * The credit notes against it, oldest first.
             *
             * @var list<array{id: string, number: string, issued_on: string, total: MoneyResource, pdf_ready: bool}>
             */
            'credit_notes' => array_values($invoice->creditNotes->map(static fn (CreditNote $creditNote): array => [
                'id' => $creditNote->public_id,
                'number' => $creditNote->number,
                'issued_on' => $creditNote->issued_on->toDateString(),
                'total' => new MoneyResource($creditNote->total),
                'pdf_ready' => $creditNote->pdf_path !== null,
            ])->all()),
            /** False until its PDF has been rendered; downloading it before then answers 409 tax_document_pdf_not_ready. */
            'pdf_ready' => $invoice->pdf_path !== null,
        ];
    }

    /**
     * The seller and buyer as copied onto an invoice or credit note when it was issued.
     *
     * @return array{seller: array<string, mixed>, buyer: array<string, mixed>}
     */
    public static function parties(Invoice|CreditNote $document): array
    {
        return [
            'seller' => [
                'name' => $document->seller_name,
                'trading_name' => $document->seller_trading_name,
                /** What the tax number is called where the store is, such as "VAT number" or "TIN". */
                'tax_registration_label' => $document->seller_tax_registration_label,
                'tax_registration_number' => $document->seller_tax_registration_number,
                'address' => $document->seller_address,
                'email' => $document->seller_email,
                'phone' => $document->seller_phone,
            ],
            'buyer' => [
                'name' => $document->buyer_name,
                'email' => $document->buyer_email,
                'phone' => $document->buyer_phone,
                'billing_address' => $document->billing_address,
                'shipping_address' => $document->shipping_address,
                /** When the buyer's details were erased from it; null while kept. */
                'erased_at' => $document->buyer_erased_at?->toIso8601String(),
            ],
        ];
    }
}
