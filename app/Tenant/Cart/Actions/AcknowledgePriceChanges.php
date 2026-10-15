<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Services\CartChanges;
use App\Tenant\Cart\Services\CartPrices;

/**
 * Records that the shopper has seen the cart's current prices, so the changes it flagged stop being flagged.
 *
 * Made from the cart version they were looking at, so a price that moved
 * after they looked is still flagged.
 */
final readonly class AcknowledgePriceChanges
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
     */
    public function handle(Shopper $shopper, ?int $expectedVersion): Cart
    {
        $cartPrices = $this->cartPrices;

        return $this->cartChanges->apply($this->findCart->handle($shopper), $expectedVersion, true, static function (Cart $cart) use ($cartPrices): void {
            $cartPrices->rememberAll($cart);
        });
    }
}
