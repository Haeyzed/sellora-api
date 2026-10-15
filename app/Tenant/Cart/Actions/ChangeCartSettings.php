<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\Data\CartSettings;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Services\CartChanges;
use App\Tenant\Cart\Services\CartPrices;
use App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException;

/**
 * Changes the currency a shopper's cart is priced in, or the country its tax is estimated to, made from the cart version they were looking at.
 *
 * Only a currency the store offers now can be chosen; if it stops being
 * offered later (its rate goes out of date), the cart is priced in the
 * store's own until it is offered again. The prices shown after a currency
 * change are the ones the shopper is taken to have seen.
 */
final readonly class ChangeCartSettings
{
    public function __construct(
        private FindCart $findCart,
        private CartChanges $cartChanges,
        private CartPrices $cartPrices,
    ) {}

    /**
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     * @throws CurrencyNotOfferedException When the store doesn't offer the currency now.
     */
    public function handle(Shopper $shopper, CartSettings $settings, ?int $expectedVersion): Cart
    {
        $cartPrices = $this->cartPrices;

        return $this->cartChanges->apply($this->findCart->handle($shopper), $expectedVersion, true, static function (Cart $cart) use ($settings, $cartPrices): void {
            if ($settings->changesDestination) {
                $cart->destination_country_code = $settings->destinationCountryCode;
            }

            if (! $settings->changesCurrency) {
                return;
            }

            $cart->currency = $settings->currency;

            if ($settings->currency !== null && $cartPrices->currencyOf($cart)->unavailable !== null) {
                throw new CurrencyNotOfferedException($settings->currency);
            }

            $cartPrices->rememberAll($cart);
        });
    }
}
