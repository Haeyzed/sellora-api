<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Pricing\Http\Resources\PricedBasketResource;
use App\Tenant\Promotions\Data\DiscountedBasket;
use App\Tenant\Promotions\Data\DiscountProposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A basket priced with its discounts: the priced lines and totals, each discount applied, and what happened to the code tried.
 *
 * @property DiscountedBasket $resource
 */
final class DiscountedBasketResource extends JsonResource
{
    public function __construct(DiscountedBasket $basket)
    {
        parent::__construct($basket);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $outcome = $this->resource->discounts;
        $currency = $outcome->currency;

        return [
            /** The lines and totals, with each line's discount already taken off before tax. */
            'basket' => new PricedBasketResource($this->resource->priced),
            /** Each discount applied, in the order they apply. */
            'discounts' => array_map(static fn (DiscountProposal $discount): array => [
                /** Where it comes from, such as "promotion". */
                'source' => $discount->source,
                /** What it is there, such as the promotion's ID. */
                'reference' => $discount->reference,
                /** What the buyer sees. */
                'label' => $discount->label,
                /** "product", "order" or "shipping". */
                'class' => $discount->class->value,
                'from_code' => $discount->isCode,
                'amount' => new MoneyResource($discount->total($currency)),
            ], $outcome->applied),
            /** The delivery charge taken off by a shipping discount. */
            'shipping_discount' => new MoneyResource($outcome->shippingDiscount),
            /**
             * The code tried, and why it doesn't apply: code_not_valid (for any code that can't be used now), first_order_only,
             * customer_group, sale_items_excluded, no_eligible_items, minimum_not_met or better_discount_applied (a combination saving more doesn't include it). Null when no code was tried.
             *
             * @var array{code: string, applied: bool, reason: string|null}|null
             */
            'code' => $outcome->code === null ? null : ['code' => $outcome->code, 'applied' => $outcome->codeApplies(), 'reason' => $outcome->codeReason],
        ];
    }
}
