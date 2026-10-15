<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Shared\Geography\Geography;
use App\Tenant\Cart\Data\CartSettings;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Exceptions\ShippingMethodNotOfferedException;
use App\Tenant\Cart\Exceptions\StateNotInCountryException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Services\CartChanges;
use App\Tenant\Cart\Services\CartPrices;
use App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Shipping\Models\ShippingMethod;

/**
 * Changes the currency a shopper's cart is priced in, where they are (country and state, for the tax and shipping estimate), or the shipping method they chose, made from the cart version they were looking at.
 *
 * Only a currency the store offers now can be chosen; if it stops being
 * offered later (its rate goes out of date), the cart is priced in the
 * store's own until it is offered again. The prices shown after a currency
 * change are the ones the shopper is taken to have seen. A state must be one
 * of the cart's country's (changing the country clears it). Only a method the
 * cart is offered as it now stands can be chosen; if that changes later, the
 * cart says so and can't check out until another is chosen.
 */
final readonly class ChangeCartSettings
{
    public function __construct(
        private FindCart $findCart,
        private CartChanges $cartChanges,
        private CartPrices $cartPrices,
        private PriceCart $priceCart,
        private Geography $geography,
        private FindStoreSettings $findStoreSettings,
    ) {}

    /**
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     * @throws CurrencyNotOfferedException When the store doesn't offer the currency now.
     * @throws StateNotInCountryException When the state isn't one of the cart's country's.
     * @throws ShippingMethodNotOfferedException When the cart isn't offered the method.
     */
    public function handle(Shopper $shopper, CartSettings $settings, ?int $expectedVersion): Cart
    {
        $cartPrices = $this->cartPrices;
        $action = $this;

        return $this->cartChanges->apply($this->findCart->handle($shopper), $expectedVersion, true, static function (Cart $cart) use ($settings, $cartPrices, $action): void {
            if ($settings->changesDestination) {
                $cart->destination_country_code = $settings->destinationCountryCode;
                $cart->destination_state_code = null;
            }

            if ($settings->changesState) {
                $cart->destination_state_code = $settings->destinationStateCode === null ? null : $action->stateOf($cart, $settings->destinationStateCode);
            }

            if ($settings->changesShippingMethod) {
                $cart->shipping_method_id = $settings->shippingMethodId === null ? null : ShippingMethod::query()->where('public_id', $settings->shippingMethodId)->value('id');
                $cart->unsetRelation('shippingMethod');
            }

            if ($settings->changesCurrency) {
                $cart->currency = $settings->currency;

                if ($settings->currency !== null && $cartPrices->currencyOf($cart)->unavailable !== null) {
                    throw new CurrencyNotOfferedException($settings->currency);
                }

                $cartPrices->rememberAll($cart);
            }

            if ($settings->changesShippingMethod && $settings->shippingMethodId !== null && ($cart->shipping_method_id === null || $action->priceCart->handle($cart)->shipping->option === null)) {
                throw new ShippingMethodNotOfferedException;
            }
        });
    }

    /**
     * The state as Geography gives it, when it is one of the cart's country's.
     *
     * @throws StateNotInCountryException
     */
    private function stateOf(Cart $cart, string $state): string
    {
        $countryCode = $cart->destination_country_code ?? $this->findStoreSettings->handle()->country_code;

        if (! $this->geography->listsStates($countryCode) || $this->geography->state($countryCode, $state) === null) {
            throw new StateNotInCountryException;
        }

        return $this->geography->stateCode($countryCode, $state);
    }
}
