<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Services;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Checkout\Data\ReplacementLine;
use App\Tenant\Checkout\Data\ReplacementRequest;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Payments\Actions\FindReturnedUnitValues;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\VariantPricer;
use App\Tenant\Returns\Data\ExchangeTerms;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnLine;
use App\Tenant\Returns\Models\ReturnSettings;
use App\Tenant\Shipping\Models\ShippingMethod;
use LogicException;

/**
 * Works out how a return's exchange would be settled now (Step 18d), as the owner decided:
 *
 * - The returned items are worth what their units cost the buyer after
 *   discounts, tax included, as a refund of them would give back.
 * - When the store's fault is confirmed, a like-for-like replacement (the
 *   same product, at the same price as the returned variant today, or as
 *   what the buyer paid when the returned variant has no price today) costs
 *   nothing beyond what the returned units did, and the delivery is free.
 * - Otherwise each replacement costs today's price with today's automatic
 *   promotions, and the delivery is charged only when the return settings
 *   say so.
 */
final readonly class ReturnExchangeTerms
{
    public function __construct(
        private FindReturnedUnitValues $findReturnedUnitValues,
        private FindExchangeRates $findExchangeRates,
        private VariantPricer $variantPricer,
    ) {}

    /**
     * @param  ShippingMethod|null  $method  How to send it; null for the way the order was sent, when anything ships.
     */
    public function for(OrderReturn $return, ?ShippingMethod $method = null): ExchangeTerms
    {
        $order = Order::query()->with(['addresses', 'customer', 'contactCorrections', 'shippingLine'])->findOrFail($return->order_id);
        $lines = $return->lines()->with(['orderLine', 'replacementVariant.product'])->get()->values();
        $values = $this->findReturnedUnitValues->handle($order, $lines->mapWithKeys(static fn (ReturnLine $line): array => [$line->orderLine->public_id => $line->quantity])->all());
        $storesFault = $return->hasConfirmedStoresFault();
        $replacementLines = [];
        $lineValues = [];
        $freeLines = [];

        foreach ($lines as $position => $line) {
            $replacement = $line->replacementVariant ?? throw new LogicException("Return {$return->public_id} is an exchange, so every line names its replacement.");
            $value = $values[$line->orderLine->public_id];
            $isFree = $storesFault && $this->isLikeForLike($order, $line->orderLine, $replacement);
            $replacementLines[] = new ReplacementLine($replacement, $line->quantity, $isFree ? $value : null);
            $lineValues[$position] = $value;

            if ($isFree) {
                $freeLines[] = $position;
            }
        }

        $ships = array_any($replacementLines, static fn (ReplacementLine $line): bool => $line->variant->requires_shipping);

        return new ExchangeTerms(
            new ReplacementRequest(
                $order,
                $return->id,
                $return->public_id,
                $return->getMorphClass(),
                $replacementLines,
                Money::sum($order->currency, array_values($values)),
                $ships ? ($method ?? $this->originalMethod($order)) : null,
                ! $storesFault && ReturnSettings::current()->charge_shipping_on_exchanges,
                $this->allowanceLabel($order->locale),
            ),
            $lineValues,
            $freeLines,
            $storesFault,
        );
    }

    /**
     * The same product, at the same price today as the returned variant (or, when the returned variant has no price today, as what the buyer paid for each returned unit), in the order's currency.
     */
    private function isLikeForLike(Order $order, OrderLine $returned, ProductVariant $replacement): bool
    {
        if ($returned->product_id !== $replacement->product_id) {
            return false;
        }

        $rate = $this->findExchangeRates->handle([$order->currency])[$order->currency] ?? null;

        if ($rate === null) {
            return false;
        }

        $returnedVariant = ProductVariant::withTrashed()->with('product')->find($returned->product_variant_id);
        $variants = $returnedVariant?->isForSale() === true ? [$replacement, $returnedVariant] : [$replacement];
        $prices = $this->variantPricer->priceAt($variants, $rate);
        $replacementPrice = $prices[$replacement->id]->unitPrice ?? null;

        if ($replacementPrice === null) {
            return false;
        }

        $returnedPrice = $returnedVariant === null ? null : ($prices[$returnedVariant->id]->unitPrice ?? null);

        return $replacementPrice->isEqualTo($returnedPrice ?? $returned->unit_price);
    }

    /**
     * How the original order was sent, while that way is still offered.
     */
    private function originalMethod(Order $order): ?ShippingMethod
    {
        $method = $order->shippingLine === null ? null : ShippingMethod::query()->find($order->shippingLine->shipping_method_id);

        return $method === null || $method->isArchived() ? null : $method;
    }

    private function allowanceLabel(string $locale): string
    {
        $label = __('returns.exchange_allowance', locale: $locale);

        return is_string($label) ? $label : 'Exchange allowance';
    }
}
