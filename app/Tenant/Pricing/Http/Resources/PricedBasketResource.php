<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Pricing\Data\AppliedTax;
use App\Tenant\Pricing\Data\LineTax;
use App\Tenant\Pricing\Data\PricedBasket;
use App\Tenant\Pricing\Data\PricedLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lines priced with their tax: the same figures a cart shows and an order saves.
 *
 * @property PricedBasket $resource
 */
final class PricedBasketResource extends JsonResource
{
    public function __construct(PricedBasket $basket)
    {
        parent::__construct($basket);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $basket = $this->resource;
        $rate = $basket->exchangeRate;

        return [
            'currency' => $basket->currency,
            /** "inclusive" when prices already include tax, "exclusive" when tax is added. */
            'tax_mode' => $basket->taxMode->value,
            /**
             * The rate base prices were converted at, with the market rate and margin it came from.
             */
            'exchange_rate' => [
                'rate' => $rate->rate->toString(),
                'source' => $rate->source->value,
                'market_rate' => $rate->marketRate?->toString(),
                'margin_percent' => $rate->marginPercent->toString(),
                'as_of' => $rate->asOf?->toIso8601String(),
            ],
            /**
             * Where tax was worked out to.
             */
            'destination' => ['country_code' => $basket->destination->countryCode, 'state_code' => $basket->destination->stateCode],
            /** "rate_table", or the key of the country plug-in that worked out the tax. */
            'tax_calculated_by' => $basket->taxCalculatedBy,
            /** True when a line had no tax rate at the destination and was charged no tax: check the store's rates. */
            'has_missing_rates' => array_any($basket->lines, static fn (PricedLine $line): bool => $line->tax->outcome->value === 'no_rate'),
            /** @var list<array<string, mixed>> */
            'lines' => array_values(array_map(static fn (PricedLine $line): array => [
                'key' => $line->key,
                'quantity' => $line->quantity,
                'unit_price' => new MoneyResource($line->price->unitPrice),
                'compare_at_price' => $line->price->compareAtPrice === null ? null : new MoneyResource($line->price->compareAtPrice),
                /** True when the merchant set this price for the currency. */
                'is_fixed_price' => $line->price->isFixed,
                'subtotal' => new MoneyResource($line->subtotal),
                'discount' => new MoneyResource($line->discount),
                /** @var array{id: string, name: string, is_exempt: bool} */
                'tax_class' => ['id' => $line->taxClass->id, 'name' => $line->taxClass->name, 'is_exempt' => $line->taxClass->isExempt],
                ...self::tax($line->tax),
            ], $basket->lines)),
            /** The delivery charge and its tax; null when none was given. */
            'shipping' => $basket->shipping === null ? null : self::tax($basket->shipping),
            /**
             * The exact sums of the lines and shipping.
             *
             * @var array{subtotal: array<string, mixed>, discount: array<string, mixed>, tax: array<string, mixed>, total: array<string, mixed>}
             */
            'totals' => [
                'subtotal' => new MoneyResource($basket->subtotal()),
                'discount' => new MoneyResource($basket->discount()),
                'tax' => new MoneyResource($basket->tax()),
                'total' => new MoneyResource($basket->total()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function tax(LineTax $tax): array
    {
        return [
            'net' => new MoneyResource($tax->net),
            'tax' => new MoneyResource($tax->tax),
            /** What the customer pays for it, tax included. */
            'total' => new MoneyResource($tax->gross),
            /** "taxed", "zero_rated", "exempt", or "no_rate" when no rate is set for its class at the destination. */
            'tax_outcome' => $tax->outcome->value,
            /** @var list<array{name: string, country_code: string, state_code: string|null, rate: string, amount: array<string, mixed>}> */
            'taxes' => array_map(static fn (AppliedTax $applied): array => [
                'name' => $applied->name,
                'country_code' => $applied->countryCode,
                'state_code' => $applied->stateCode,
                'rate' => $applied->ratePercent->toString(),
                'amount' => new MoneyResource($applied->amount),
            ], $tax->taxes),
        ];
    }
}
