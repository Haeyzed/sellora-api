<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Actions;

use App\Tenant\Cart\Actions\FindCart;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Checkout\Data\CheckoutQuote;
use App\Tenant\Checkout\Enums\CheckoutStatus;
use App\Tenant\Checkout\Exceptions\CheckoutCannotStartException;
use App\Tenant\Checkout\Exceptions\GuestCheckoutNotAllowedException;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Checkout\Services\CheckoutQuotes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * Starts checking out the shopper's cart, or picks up the checkout they already started for it, and returns its quote (Step 16).
 *
 * A signed-in customer's checkout always uses their account's email; their
 * name and phone start as their account's. A guest may check out only while
 * the store allows guest checkout. Nothing is held: stock and promotion uses
 * are held only once the order is placed.
 */
final readonly class StartCheckout
{
    public function __construct(
        private FindCart $findCart,
        private FindCheckout $findCheckout,
        private CheckoutQuotes $checkoutQuotes,
    ) {}

    /**
     * @throws GuestCheckoutNotAllowedException
     * @throws CartNotFoundException When the shopper has no cart.
     * @throws CheckoutCannotStartException When the cart is empty.
     */
    public function handle(Shopper $shopper): CheckoutQuote
    {
        $this->findCheckout->ensureAllowed($shopper);
        $cart = $this->findCart->handle($shopper);

        if (! $cart->lines()->exists()) {
            throw new CheckoutCannotStartException;
        }

        $checkout = DB::transaction(static function () use ($cart, $shopper): Checkout {
            // One open checkout per cart, even when two tabs start at once.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['checkout-start:'.$cart->id]);

            $checkout = Checkout::query()->where('cart_id', $cart->id)->where('status', CheckoutStatus::Open->value)->first();
            $customer = $shopper->customer;

            if ($checkout === null) {
                $checkout = new Checkout;
                $checkout->forceFill([
                    'cart_id' => $cart->id,
                    'customer_id' => $customer?->id,
                    'status' => CheckoutStatus::Open,
                    'contact_name' => $customer?->name,
                    'contact_phone' => $customer?->phone,
                    'locale' => App::getLocale(),
                ]);
            }

            $checkout->forceFill([
                'contact_email' => $customer === null ? $checkout->contact_email : mb_strtolower((string) $customer->email),
                'last_activity_at' => CarbonImmutable::now(),
            ])->save();

            return $checkout;
        });

        return $this->checkoutQuotes->refresh($checkout->refresh());
    }
}
