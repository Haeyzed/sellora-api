<?php

declare(strict_types=1);

namespace App\Tenant\Pricing;

use App\Shared\Money\Money;
use App\Tenant\Pricing\Actions\CalculateTax;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\Actions\FindVariantTaxClasses;
use App\Tenant\Pricing\Data\PricedBasket;
use App\Tenant\Pricing\Data\PricedLine;
use App\Tenant\Pricing\Data\PricingRequest;
use App\Tenant\Pricing\Data\TaxableLine;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Pricing\Data\TaxClassData;
use App\Tenant\Pricing\Data\TaxRequest;
use App\Tenant\Pricing\Enums\TaxLineKind;
use App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException;
use App\Tenant\Pricing\Exceptions\DiscountExceedsLineException;
use App\Tenant\Pricing\Exceptions\VariantNotPricedException;
use App\Tenant\Pricing\Models\TaxClass;
use App\Tenant\Settings\Actions\FindStoreSettings;
use InvalidArgumentException;

/**
 * Prices a set of lines with their tax: the one calculation Cart shows, Checkout charges and Orders save (section 3.3).
 *
 * Unit prices come from VariantPricer (fixed price, or the base price
 * converted per unit). Each line's taxable amount is unit price × quantity −
 * its discount, taxed per line by CalculateTax; shipping is one more taxable
 * line at the store's shipping tax class, untaxed when there is none. Every
 * total is the exact sum of its lines.
 */
final readonly class PriceCalculator
{
    /** The key the delivery charge is taxed under; no line may use it. */
    public const string SHIPPING_KEY = '#shipping';

    public function __construct(
        private FindStoreSettings $findStoreSettings,
        private FindExchangeRates $findExchangeRates,
        private VariantPricer $variantPricer,
        private FindVariantTaxClasses $findVariantTaxClasses,
        private CalculateTax $calculateTax,
    ) {}

    /**
     * @throws CurrencyNotOfferedException When the store doesn't offer the currency now.
     * @throws VariantNotPricedException When a line's variant has no price.
     * @throws DiscountExceedsLineException When a line's discount is more than the line.
     * @throws InvalidArgumentException When a line uses the shipping key, two lines share a key, or a discount is in another currency.
     */
    public function quote(PricingRequest $request): PricedBasket
    {
        $settings = $this->findStoreSettings->handle();
        $rate = $this->findExchangeRates->handle([$request->currency])[$request->currency] ?? throw new CurrencyNotOfferedException($request->currency);
        $variants = array_map(static fn ($line) => $line->variant, $request->lines);
        $prices = $this->variantPricer->priceAt($variants, $rate);
        $taxClasses = $this->findVariantTaxClasses->handle(array_values(array_unique(array_map(static fn ($variant): int => $variant->id, $variants))));

        $origin = new TaxAddress($settings->address_country_code ?? $settings->country_code, $settings->address_country_code === null ? null : $settings->address_state_code);
        $destination = $request->destination ?? $origin;
        $taxableLines = [];
        $lines = [];

        foreach ($request->lines as $line) {
            if ($line->key === self::SHIPPING_KEY) {
                throw new InvalidArgumentException('The key "'.self::SHIPPING_KEY.'" is kept for shipping.');
            }

            $price = $prices[$line->variant->id] ?? throw new VariantNotPricedException($line->variant->public_id);
            $subtotal = $price->unitPrice->multiplyBy($line->quantity);
            $discount = $line->discount ?? Money::zero($request->currency);

            if ($discount->isGreaterThan($subtotal)) {
                throw new DiscountExceedsLineException($line->key);
            }

            $taxableLines[] = new TaxableLine($line->key, TaxLineKind::Goods, $subtotal->subtract($discount), $taxClasses[$line->variant->id], $line->quantity);
            $lines[$line->key] = [$line, $price, $subtotal, $discount];
        }

        $shippingClass = $request->shipping === null ? null : $this->shippingTaxClass();

        if ($request->shipping !== null) {
            $taxableLines[] = new TaxableLine(self::SHIPPING_KEY, TaxLineKind::Shipping, $request->shipping, $shippingClass ?? new TaxClassData('', 'Shipping not taxed', true));
        }

        $tax = $this->calculateTax->handle(new TaxRequest($request->currency, $settings->tax_mode, $origin, $destination, $taxableLines, $request->customer));
        $priced = [];

        foreach ($lines as $key => [$line, $price, $subtotal, $discount]) {
            $priced[$key] = new PricedLine((string) $key, $line->variant->id, $line->quantity, $price, $subtotal, $discount, $taxClasses[$line->variant->id], $tax->line((string) $key));
        }

        return new PricedBasket(
            $request->currency,
            $settings->tax_mode,
            $rate,
            $priced,
            $request->shipping === null ? null : $tax->line(self::SHIPPING_KEY),
            $destination,
            $tax->calculatedBy,
        );
    }

    /**
     * The class shipping is taxed at, or null when the store doesn't tax shipping.
     */
    private function shippingTaxClass(): ?TaxClassData
    {
        $taxClass = TaxClass::query()->where('used_for_shipping', true)->first();

        return $taxClass === null ? null : TaxClassData::of($taxClass);
    }
}
