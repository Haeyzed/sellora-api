<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Checkout\Data\ReplacementLine;
use App\Tenant\Returns\Data\ExchangePreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a return's exchange would come to if confirmed now: its replacement order priced, and how the returned items settle it. Confirm it with its quote_hash.
 *
 * @property-read ExchangePreview $resource
 */
final class ExchangePreviewResource extends JsonResource
{
    public function __construct(ExchangePreview $preview)
    {
        parent::__construct($preview);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $preview = $this->resource;
        $quote = $preview->quote;
        $terms = $preview->terms;

        return [
            /** Why it can't be confirmed now, as error codes: replacement_not_available, replacement_out_of_stock, replacement_currency_not_offered or replacement_shipping_unavailable. Empty when it can. */
            'issues' => $quote->issues,
            /** Whether the store's fault was confirmed: a like-for-like replacement and its delivery are then free. */
            'stores_fault' => $terms->storesFault,
            /**
             * Each replacement, as priced: today's price with today's automatic promotions, less any allowance. free_like_for_like: the same product at the same price, sent free because the store was at fault.
             *
             * @var list<array{variant: array{id: string, sku: string|null}, quantity: int, free_like_for_like: bool, returned_value: MoneyResource, total: MoneyResource|null}>
             */
            'lines' => array_map(static fn (ReplacementLine $line, int $position): array => [
                'variant' => ['id' => $line->variant->public_id, 'sku' => $line->variant->sku],
                'quantity' => $line->quantity,
                'free_like_for_like' => in_array($position, $terms->freeLines, true),
                'returned_value' => new MoneyResource($terms->lineValues[$position]),
                'total' => ($total = $quote->lineTotal($position)) === null ? null : new MoneyResource($total),
            ], $terms->request->lines, array_keys($terms->request->lines)),
            /**
             * How the replacement is sent; null when nothing ships. charged: whether the buyer pays for it (never when the store was at fault).
             *
             * @var array{method: string, amount: MoneyResource|null, charged: bool}|null
             */
            'shipping' => $quote->shipping === null ? null : [
                'method' => $quote->shipping->method->public_id,
                'amount' => $quote->shipping->amount === null ? null : new MoneyResource($quote->shipping->amount),
                'charged' => $terms->request->chargesShipping,
            ],
            /** What the returned units cost the buyer after their discounts. */
            'returned_value' => new MoneyResource($quote->returnedValue),
            /** What the replacement order comes to, delivery and tax included. */
            'total' => new MoneyResource($quote->total),
            /** What the returned items pay toward it: the smaller of the two above. */
            'credit' => new MoneyResource($quote->credit),
            /** What the buyer still owes on the replacement, paid the usual way. */
            'amount_due' => new MoneyResource($quote->amountDue),
            /** What goes back to the buyer, to the refund destination they chose, once the replacement is paid. */
            'difference' => new MoneyResource($preview->difference),
            /** What the store keeps: what a free like-for-like replacement didn't use. */
            'kept' => new MoneyResource($preview->kept),
            /** Send this to confirm the exchange as shown. */
            'quote_hash' => $quote->hash,
        ];
    }
}
