<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Resources;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyResource;
use App\Tenant\Cart\Data\PricedCart;
use App\Tenant\Cart\Data\PricedCartLine;
use App\Tenant\Cart\Data\SkippedLine;
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
     * @param  list<SkippedLine>|null  $skipped  After a merge, the guest's lines that didn't come across.
     */
    public function __construct(PricedCart $cart, private readonly ?string $token = null, private readonly ?array $skipped = null)
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
            /**
             * After merging a guest cart: the guest's lines that didn't come across, and why (variant_not_available, variant_out_of_stock or cart_full).
             * Lines for a variant already in the cart keep the larger quantity. Only in the merge's response.
             *
             * @var list<array{variant: array{id: string, sku: string|null}, quantity: int, reason: string}>
             */
            'skipped_lines' => $this->when($this->skipped !== null, fn (): array => array_map(static fn (SkippedLine $line): array => [
                'variant' => ['id' => $line->variant->public_id, 'sku' => $line->variant->sku],
                'quantity' => $line->quantity,
                'reason' => $line->reason,
            ], $this->skipped ?? [])),
            /** The currency every amount is in. */
            'currency' => $currency,
            /**
             * The currency the shopper chose when the store can't price in it right now (for example while its exchange rate is out of date):
             * the cart is in the store's own currency meanwhile, and goes back to it once it is offered again. Null otherwise.
             */
            'chosen_currency_unavailable' => $priced->currency->unavailable,
            /** True when checkout can start: there is something in the cart, every line can be bought, and shipping is settled (nothing ships, or an offered method is chosen). */
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
                /** "product", "order" or "shipping". */
                'class' => $discount->class->value,
                'from_code' => $discount->isCode,
                'amount' => new MoneyResource($discount->total($currency)),
            ], $priced->basket->discounts->applied),
            /**
             * The promotion code on the cart, and why it doesn't apply: code_not_valid (it can't be used any more), first_order_only, customer_group,
             * sale_items_excluded, no_eligible_items, minimum_not_met, customer_limit_reached, email_needed (a guest gives one at checkout),
             * usage_limit_reached or better_discount_applied. Null when there is no code.
             *
             * @var array{code: string, applied: bool, reason: string|null}|null
             */
            'code' => self::code($priced),
            /**
             * What the lines that can be bought come to, with the chosen shipping (after free-shipping promotions, with its tax) when it has a price.
             *
             * @var array{subtotal: MoneyResource, discount: MoneyResource, shipping: MoneyResource, tax: MoneyResource, total: MoneyResource}
             */
            'totals' => [
                'subtotal' => new MoneyResource($totals?->subtotal() ?? Money::zero($currency)),
                'discount' => new MoneyResource($totals?->discount() ?? Money::zero($currency)),
                /** Shipping as charged, tax included where prices include it, after free-shipping promotions. */
                'shipping' => new MoneyResource($totals?->shipping->gross ?? Money::zero($currency)),
                'tax' => new MoneyResource($totals?->tax() ?? Money::zero($currency)),
                'total' => new MoneyResource($totals?->total() ?? Money::zero($currency)),
            ],
            /**
             * Tax is an estimate until checkout has the delivery address: worked out to a chosen pickup's address, the place the shopper gave, or the store's own country.
             */
            'tax' => [
                'is_estimate' => true,
                /** "inclusive" when prices already include tax, "exclusive" when it is added; null before anything is priced. */
                'mode' => $totals?->taxMode->value,
                /** The country the estimate is for; null before anything is priced. */
                'country_code' => $priced->taxDestination?->countryCode,
                /** The country the shopper gave; null for the store's own. */
                'chosen_country_code' => $cart->destination_country_code,
                /** The state the shopper gave; null for none. */
                'chosen_state_code' => $cart->destination_state_code,
            ],
            /**
             * Shipping, an estimate until checkout has the full address: quoted to the country (and state) the shopper gave, or the store's own.
             * GET /cart/shipping-options lists what the cart is offered; choose one with PATCH /cart (shipping_method).
             *
             * @var array{required: bool, is_estimate: bool, country_code: string, state_code: string|null, reason: string|null, method: array{id: string, name: string, fulfilment: string}|null, chosen_method_unavailable: bool, calculated_at_checkout: bool, amount: MoneyResource|null, discount: MoneyResource|null}
             */
            'shipping' => self::shipping($priced, $locale),
        ];
    }

    /**
     * @return array{required: bool, is_estimate: bool, country_code: string, state_code: string|null, reason: string|null, method: array{id: string, name: string, fulfilment: string}|null, chosen_method_unavailable: bool, calculated_at_checkout: bool, amount: MoneyResource|null, discount: MoneyResource|null}
     */
    private static function shipping(PricedCart $priced, string $locale): array
    {
        $shipping = $priced->shipping;
        $chosen = $shipping->chosen;

        return [
            /** False when nothing in the cart ships: no method or address is needed. */
            'required' => $shipping->quote->required,
            'is_estimate' => true,
            'country_code' => $shipping->destination->countryCode,
            'state_code' => $shipping->destination->stateCode,
            /** "no_zone" when the store doesn't ship there (a pickup may still be offered); null otherwise. */
            'reason' => $shipping->quote->required ? $shipping->quote->reason : null,
            /** The method the shopper chose; null before they choose. */
            'method' => $chosen === null ? null : ['id' => $chosen->public_id, 'name' => $chosen->getTranslation('name', $locale), 'fulfilment' => $chosen->fulfilment->value],
            /** True when the chosen method isn't offered any more (where the shopper is, for what is in the cart, or at all): choose another to check out. */
            'chosen_method_unavailable' => $shipping->chosenUnavailable(),
            /** True for a live carrier rate, priced at checkout once the full address is known. */
            'calculated_at_checkout' => $shipping->option?->isCalculatedAtCheckout() ?? false,
            /** The chosen method's price before free-shipping promotions; null when none is chosen, it isn't offered, or it is calculated at checkout. */
            'amount' => $shipping->option?->amount === null ? null : new MoneyResource($shipping->option->amount),
            /** What free-shipping promotions take off it; null when nothing is charged for shipping. */
            'discount' => $shipping->discount === null ? null : new MoneyResource($shipping->discount),
        ];
    }

    /**
     * @return array{code: string, applied: bool, reason: string|null}|null
     */
    private static function code(PricedCart $priced): ?array
    {
        $code = $priced->cart->promotion_code;

        if ($code === null) {
            return null;
        }

        $outcome = $priced->basket?->discounts;

        // With nothing that can be bought, there is nothing for it to apply to.
        return $outcome === null
            ? ['code' => $code, 'applied' => false, 'reason' => 'no_eligible_items']
            : ['code' => $code, 'applied' => $outcome->codeApplies(), 'reason' => $outcome->codeReason];
    }

    /**
     * Carries the cart's version as the ETag, to send back in If-Match.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setEtag((string) $this->resource->cart->version);
    }
}
