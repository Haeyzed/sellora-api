<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Resources;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyResource;
use App\Tenant\Cart\Data\PricedCart;
use App\Tenant\Cart\Data\PricedCartLine;
use App\Tenant\Promotions\Data\DiscountProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shopper's cart as it stands now: each line and whether it can be bought, today's prices and discounts, estimated tax, and the version to send back (If-Match) with a change.
 *
 * @property PricedCart $resource
 */
final class CartResource extends JsonResource
{
    /**
     * @param  string|null  $token  A new guest cart's token: in this response only, never again.
     */
    public function __construct(PricedCart $cart, private readonly ?string $token = null)
    {
        parent::__construct($cart);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $priced = $this->resource;
        $cart = $priced->cart;
        $currency = $priced->currency->code();
        $locale = app()->getLocale();
        $totals = $priced->basket?->priced;

        return [
            'id' => $cart->public_id,
            /** Goes up with every change; send it back in If-Match (it is also the ETag) to change or remove lines. */
            'version' => $cart->version,
            /**
             * A new guest cart's token. Shown only in the response that created the cart: keep it, and send it in the X-Cart-Token header.
             */
            'token' => $this->when($this->token !== null, $this->token),
            /** The currency every amount is in. */
            'currency' => $currency,
            /**
             * The currency the shopper chose when the store can't price in it right now (for example while its exchange rate is out of date):
             * the cart is in the store's own currency meanwhile, and goes back to it once it is offered again. Null otherwise.
             */
            'chosen_currency_unavailable' => $priced->currency->unavailable,
            /** True when checkout can start: there is something in the cart, and every line can be bought. */
            'can_check_out' => $priced->canCheckOut(),
            /**
             * Its lines, oldest first. Status is "available"; "unavailable" when it is no longer for sale; "out_of_stock" when none is left and it
             * takes no backorders. Lines that can't be bought have no prices and are left out of the totals. Backorder is true when it can be
             * bought but none is in stock: it is sent when it arrives. Prices include tax in a tax-inclusive store; total is after the line's
             * discount, tax included. price_changed says the merchant changed the unit price since the shopper last saw it (what it was and
             * what it is now, at today's rate; exchange rates moving never count), until the line is changed or price changes are acknowledged.
             *
             * @var list<array{id: string, variant: array{id: string, sku: string|null, product: array{id: string, slug: string, name: string}}, quantity: int, added_at: string|null, status: string, backorder: bool, unit_price: MoneyResource|null, compare_at_price: MoneyResource|null, subtotal: MoneyResource|null, discount: MoneyResource|null, total: MoneyResource|null, price_changed: array{was: MoneyResource, now: MoneyResource}|null}>
             */
            'lines' => array_map(static fn (PricedCartLine $line): array => [
                'id' => $line->line->public_id,
                'variant' => [
                    'id' => $line->line->variant->public_id,
                    'sku' => $line->line->variant->sku,
                    'product' => [
                        'id' => $line->line->variant->product->public_id,
                        'slug' => $line->line->variant->product->slug,
                        'name' => $line->line->variant->product->getTranslation('name', $locale),
                    ],
                ],
                'quantity' => $line->line->quantity,
                'added_at' => $line->line->created_at?->toIso8601String(),
                'status' => $line->status->value,
                'backorder' => $line->backorder,
                'unit_price' => $line->priced === null ? null : new MoneyResource($line->priced->price->unitPrice),
                'compare_at_price' => $line->priced?->price->compareAtPrice === null ? null : new MoneyResource($line->priced->price->compareAtPrice),
                'subtotal' => $line->priced === null ? null : new MoneyResource($line->priced->subtotal),
                'discount' => $line->priced === null ? null : new MoneyResource($line->priced->discount),
                'total' => $line->priced === null ? null : new MoneyResource($line->priced->total()),
                'price_changed' => $line->priceChange === null ? null : ['was' => new MoneyResource($line->priceChange->was), 'now' => new MoneyResource($line->priceChange->now)],
            ], $priced->lines),
            /**
             * Each discount applied, in the order they apply.
             *
             * @var list<array{label: string, class: string, from_code: bool, amount: MoneyResource}>
             */
            'discounts' => $priced->basket === null ? [] : array_map(static fn (DiscountProposal $discount): array => [
                'label' => $discount->label,
                /** "product" or "order". */
                'class' => $discount->class->value,
                'from_code' => $discount->isCode,
                'amount' => new MoneyResource($discount->total($currency)),
            ], $priced->basket->discounts->applied),
            /**
             * What the lines that can be bought come to. Delivery is added at checkout.
             *
             * @var array{subtotal: MoneyResource, discount: MoneyResource, tax: MoneyResource, total: MoneyResource}
             */
            'totals' => [
                'subtotal' => new MoneyResource($totals?->subtotal() ?? Money::zero($currency)),
                'discount' => new MoneyResource($totals?->discount() ?? Money::zero($currency)),
                'tax' => new MoneyResource($totals?->tax() ?? Money::zero($currency)),
                'total' => new MoneyResource($totals?->total() ?? Money::zero($currency)),
            ],
            /**
             * Tax is an estimate until checkout has the delivery address: worked out to the country the shopper gave, or the store's own.
             *
             * @var array{is_estimate: bool, mode: string|null, country_code: string|null, chosen_country_code: string|null}
             */
            'tax' => [
                'is_estimate' => true,
                /** "inclusive" when prices already include tax, "exclusive" when it is added; null before anything is priced. */
                'mode' => $totals?->taxMode->value,
                /** The country the estimate is for; null before anything is priced. */
                'country_code' => $priced->taxDestination?->countryCode,
                /** The country the shopper gave; null for the store's own. */
                'chosen_country_code' => $cart->destination_country_code,
            ],
        ];
    }

    /**
     * Carries the cart's version as the ETag, to send back in If-Match.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setEtag((string) $this->resource->cart->version);
    }
}
