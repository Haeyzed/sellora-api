<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Shipping\Data\ShippingOption;
use App\Tenant\Shipping\Data\ShippingQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The ways a basket can be shipped to a destination, and what each costs.
 *
 * @property ShippingQuote $resource
 */
final class ShippingQuoteResource extends JsonResource
{
    public function __construct(ShippingQuote $quote)
    {
        parent::__construct($quote);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quote = $this->resource;

        return [
            /** False when nothing in it ships (services, digital goods): no method or address is needed. */
            'required' => $quote->required,
            /** "no_zone" when no shipping zone covers the destination (pickups may still be offered); null otherwise. */
            'reason' => $quote->reason,
            /** The total weight of the lines that ship. */
            'weight_grams' => $quote->weightGrams,
            /** The lines that ship, after discounts, as displayed: what value brackets and "free over" look at. */
            'value' => new MoneyResource($quote->value),
            /**
             * The zone's methods by position, then pickups. A method none of whose brackets covers the basket isn't listed.
             *
             * @var list<ShippingOptionResource>
             */
            'options' => array_map(static fn (ShippingOption $option): ShippingOptionResource => new ShippingOptionResource($option), $quote->options),
        ];
    }
}
