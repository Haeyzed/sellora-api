<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Resources;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyResource;
use App\Tenant\Shipping\Models\ShippingMethod;
use App\Tenant\Shipping\Models\ShippingMethodAmount;
use App\Tenant\Shipping\Models\ShippingRate;
use App\Tenant\Shipping\Models\ShippingRateAmount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipping method as staff see it: how it reaches the shopper, its price brackets and exact amounts, and where its brackets leave it unoffered.
 *
 * @property ShippingMethod $resource
 */
final class ShippingMethodResource extends JsonResource
{
    public function __construct(ShippingMethod $method)
    {
        parent::__construct($method);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $method = $this->resource;
        $money = static fn (int $minor): MoneyResource => new MoneyResource(Money::ofMinor($minor, $method->currency));

        return [
            'id' => $method->public_id,
            /** Null for a pickup, or an archived method whose zone was deleted. */
            'zone' => $method->zone === null ? null : ['id' => $method->zone->public_id, 'name' => $method->zone->name],
            /** By language. */
            'name' => $method->getTranslations('name'),
            /** By language. */
            'description' => $method->getTranslations('description'),
            'fulfilment' => $method->fulfilment->value,
            'pricing' => $method->pricing->value,
            /** Null for never. */
            'free_over' => $method->free_over === null ? null : new MoneyResource($method->free_over),
            /**
             * Exact "free over" thresholds in other currencies.
             *
             * @var list<array{currency: string, free_over: MoneyResource}>
             */
            'currency_amounts' => array_values($method->currencyAmounts->map(static fn (ShippingMethodAmount $amount): array => ['currency' => $amount->currency_code, 'free_over' => new MoneyResource($amount->free_over)])->all()),
            /**
             * Its brackets, by where they start: [from, to) in grams or the base currency's smallest unit; a null "to" means and up.
             *
             * @var list<array{from: int, to: int|null, amount: MoneyResource, currency_amounts: list<array{currency: string, amount: MoneyResource}>}>
             */
            'rates' => array_values($method->rates->map(static fn (ShippingRate $rate): array => [
                'from' => $rate->range_from,
                'to' => $rate->range_to,
                'amount' => $money($rate->amount),
                'currency_amounts' => array_values($rate->currencyAmounts->map(static fn (ShippingRateAmount $amount): array => ['currency' => $amount->currency_code, 'amount' => new MoneyResource($amount->price)])->all()),
            ])->all()),
            /**
             * Where its brackets leave it unoffered: below the first, between two, above the last when that one ends. A shopper's basket there doesn't get this method.
             *
             * @var list<array{code: string, from: int, to: int|null}>
             */
            'warnings' => array_map(static fn (array $gap): array => ['code' => 'not_offered', ...$gap], $method->uncoveredRanges()),
            /** Only for a pickup: where it is collected, offered to shoppers in that country. */
            'pickup' => $method->stock_location_id === null ? null : [
                'stock_location' => ['id' => $method->stockLocation?->public_id, 'name' => $method->stockLocation?->name],
                'country_code' => $method->pickup_country_code,
                'state_code' => $method->pickup_state_code,
                'address_line1' => $method->pickup_address_line1,
                'city' => $method->pickup_city,
                'postal_code' => $method->pickup_postal_code,
                /** By language. */
                'instructions' => $method->getTranslations('pickup_instructions'),
            ],
            'position' => $method->position,
            'is_active' => $method->is_active,
            /** When it was archived; it is then never offered and can't change. */
            'archived_at' => $method->archived_at?->toIso8601String(),
        ];
    }
}
