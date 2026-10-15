<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Services;

use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Models\Cart;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Makes one change to a cart at a time, so two tabs (or two devices) editing one cart never lose a change or slip past its limits.
 *
 * Every change locks the cart's row (FOR NO KEY UPDATE, which still lets
 * lines be added under it), then raises its version. Changes that replace or
 * remove something name the version they were made from (If-Match); one made
 * from an out-of-date view is refused rather than undoing the other tab's
 * change. Adding needs no version: adds simply add up.
 */
final readonly class CartChanges
{
    public const string ROW_LOCK = 'for no key update';

    /**
     * @param  int|null  $expectedVersion  The version the change was made from (If-Match); null when none was sent.
     * @param  Closure(Cart): void  $change  Runs with the cart locked and its current version; what it throws reaches the caller.
     *
     * @param-immediately-invoked-callable $change
     *
     * @throws CartVersionRequiredException When a version is needed and none was sent.
     * @throws CartChangedException When the cart has changed since that version.
     */
    public function apply(Cart $cart, ?int $expectedVersion, bool $needsVersion, Closure $change): Cart
    {
        if ($needsVersion && $expectedVersion === null) {
            throw new CartVersionRequiredException;
        }

        return $cart->getConnection()->transaction(static function () use ($cart, $expectedVersion, $change): Cart {
            $locked = Cart::query()->whereKey($cart->id)->lock(self::ROW_LOCK)->firstOrFail();

            if ($expectedVersion !== null && $expectedVersion !== $locked->version) {
                throw new CartChangedException;
            }

            $change($locked);

            $locked->forceFill(['version' => $locked->version + 1, 'last_activity_at' => CarbonImmutable::now()])->save();

            return $locked;
        });
    }
}
