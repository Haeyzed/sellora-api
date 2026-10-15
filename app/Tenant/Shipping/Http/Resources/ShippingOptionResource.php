<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Shipping\Data\ShippingOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One way a basket can be shipped, in the request's language, and what it costs.
 *
 * @property ShippingOption $resource
 */
final class ShippingOptionResource extends JsonResource
{
    public function __construct(ShippingOption $option)
    {
        parent::__construct($option);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $option = $this->resource;
        $method = $option->method;
        $description = $method->getTranslation('description', app()->getLocale());

        return [
            /** The shipping method's ID, to choose it. */
            'id' => $method->public_id,
            'name' => $method->getTranslation('name', app()->getLocale()),
            'description' => $description === '' ? null : $description,
            /** "ship", "own_delivery" or "pickup". */
            'fulfilment' => $method->fulfilment->value,
            /** What it costs; null when it is calculated at checkout, from the full address. */
            'amount' => $option->amount === null ? null : new MoneyResource($option->amount),
            /** True for a live carrier rate, quoted only once the full address is known. */
            'calculated_at_checkout' => $option->isCalculatedAtCheckout(),
            /** True when the basket reached the method's "free over" threshold. */
            'is_free' => $option->isFree,
            /** When it would arrive (or be ready to collect): the store's working days from today, as dates in the store's timezone; null when the method gives no estimate. */
            'estimate' => $option->estimate === null ? null : [
                'min_days' => $option->estimate->minDays,
                'max_days' => $option->estimate->maxDays,
                'earliest' => $option->estimate->earliest->toDateString(),
                'latest' => $option->estimate->latest->toDateString(),
            ],
            /** Only for a pickup: where it is collected. */
            'pickup' => $method->pickup_country_code === null ? null : [
                'address_line1' => $method->pickup_address_line1,
                'city' => $method->pickup_city,
                'state_code' => $method->pickup_state_code,
                'postal_code' => $method->pickup_postal_code,
                'country_code' => $method->pickup_country_code,
                'instructions' => $method->getTranslation('pickup_instructions', app()->getLocale()) ?: null,
            ],
        ];
    }
}
