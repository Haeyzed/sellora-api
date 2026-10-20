<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\CreditNoteLine;
use App\Tenant\Orders\Models\CreditNoteTax;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A credit note, exactly as it was issued: the invoice it reduces, what part of the sale was given back, with its taxes.
 *
 * @property CreditNote $resource
 */
final class CreditNoteResource extends JsonResource
{
    public function __construct(CreditNote $creditNote)
    {
        parent::__construct($creditNote);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creditNote = $this->resource;

        return [
            'id' => $creditNote->public_id,
            /** As printed, such as "CN-2026-000007"; "TEST-CN-1042-1" for a test order's. */
            'number' => $creditNote->number,
            'is_test' => $creditNote->is_test,
            'invoice' => ['id' => $creditNote->invoice->public_id, 'number' => $creditNote->invoice->number],
            'order' => ['id' => $creditNote->order->public_id, 'number' => $creditNote->order->number],
            /** The refund it was issued for. */
            'refund' => $creditNote->refund_reference,
            /** The issue date printed, in the store's timezone when it was issued (YYYY-MM-DD). */
            'issued_on' => $creditNote->issued_on->toDateString(),
            'issued_at' => $creditNote->issued_at->toIso8601String(),
            'locale' => $creditNote->locale,
            'currency' => $creditNote->currency,
            'tax_mode' => $creditNote->tax_mode->value,
            ...InvoiceResource::parties($creditNote),
            /**
             * @var list<array{kind: string, name: string, variant_name: string|null, sku: string|null, quantity: int, net: MoneyResource, tax: MoneyResource, total: MoneyResource, taxes: list<array{name: string, rate: string, tax: MoneyResource}>}>
             */
            'lines' => array_values($creditNote->lines->map(static fn (CreditNoteLine $line): array => [
                /** "item" (returned or given back on a line), "shipping", or "adjustment" (a price reduction). */
                'kind' => $line->kind->value,
                'name' => $line->name,
                'variant_name' => $line->variant_name,
                'sku' => $line->sku,
                /** Units returned; 0 for a share of a line given back without units. */
                'quantity' => $line->quantity,
                'net' => new MoneyResource($line->net),
                'tax' => new MoneyResource($line->tax),
                'total' => new MoneyResource($line->total),
                'taxes' => array_values($line->taxes->map(static fn (CreditNoteTax $tax): array => ['name' => $tax->name, 'rate' => $tax->rate_percent->toString(), 'tax' => new MoneyResource($tax->tax)])->all()),
            ])->all()),
            /**
             * @var array{net: MoneyResource, tax: MoneyResource, total: MoneyResource}
             */
            'totals' => [
                'net' => new MoneyResource($creditNote->net),
                'tax' => new MoneyResource($creditNote->tax),
                'total' => new MoneyResource($creditNote->total),
            ],
            'footer' => $creditNote->footer,
            /** False until its PDF has been rendered; downloading it before then answers 409 tax_document_pdf_not_ready. */
            'pdf_ready' => $creditNote->pdf_path !== null,
        ];
    }
}
