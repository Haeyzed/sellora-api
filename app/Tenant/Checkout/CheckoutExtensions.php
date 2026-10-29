<?php

declare(strict_types=1);

namespace App\Tenant\Checkout;

use App\Shared\Features\Features;
use App\Shared\Features\FeatureState;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Contracts\CheckoutPaymentRefunds;
use App\Tenant\Checkout\Contracts\CheckoutValidator;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Where modules plug into checkout (section 6): payment methods and checkout validators, registered from their service providers under their feature key.
 *
 * Core never imports a module: it asks here, and gets only what belongs to
 * core (no feature key) or to a module enabled for the current store.
 */
final class CheckoutExtensions
{
    /** @var list<array{class-string<CheckoutPaymentMethod>, string|null}> */
    private array $paymentMethods = [];

    /** @var list<array{class-string<CheckoutValidator>, string|null}> */
    private array $validators = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Registers a payment method, refusing one that would share a key with a method already registered: a hold saved under that key could otherwise be committed, released or given back by the wrong one. Modules register from their service providers, so a clash stops the app at boot.
     *
     * @param  class-string<CheckoutPaymentMethod>  $method
     * @param  string|null  $featureKey  The module's; null for core's own.
     *
     * @throws LogicException When it shares a key with a method already registered.
     */
    public function addPaymentMethod(string $method, ?string $featureKey = null): void
    {
        $new = $this->paymentMethod($method);

        foreach ($this->paymentMethods as [$registered]) {
            $existing = $this->paymentMethod($registered);
            $shared = [
                ...array_filter($new->keys(), $existing->handles(...)),
                ...array_filter($existing->keys(), $new->handles(...)),
            ];

            if ($shared !== []) {
                throw new LogicException("The payment methods {$registered} and {$method} both use the key \"{$shared[array_key_first($shared)]}\": each key must belong to one payment method only.");
            }
        }

        $this->paymentMethods[] = [$method, $featureKey];
    }

    /**
     * @param  class-string<CheckoutValidator>  $validator
     * @param  string|null  $featureKey  The module's; null for core's own.
     */
    public function addValidator(string $validator, ?string $featureKey = null): void
    {
        $this->validators[] = [$validator, $featureKey];
    }

    /**
     * The payment methods the current store may offer, in the order they were registered.
     *
     * @return list<CheckoutPaymentMethod>
     */
    public function paymentMethods(): array
    {
        $methods = [];

        foreach ($this->paymentMethods as [$class, $featureKey]) {
            if ($featureKey === null || $this->features()->isEnabled($featureKey)) {
                $method = $this->container->make($class);
                $methods[] = $method instanceof CheckoutPaymentMethod ? $method : throw new LogicException("{$class} is not a checkout payment method.");
            }
        }

        return $methods;
    }

    /**
     * The registered payment method a key belongs to, whether or not its module is enabled now: a hold made while it was must still be committed or released.
     */
    public function paymentMethodFor(string $key): ?CheckoutPaymentMethod
    {
        foreach ($this->paymentMethods as [$class]) {
            $method = $this->container->make($class);

            if ($method instanceof CheckoutPaymentMethod && $method->handles($key)) {
                return $method;
            }
        }

        return null;
    }

    /**
     * What gives back a hold saved under a payment method key: its registered method, when it can give back (CheckoutPaymentRefunds) and its module isn't Unavailable to the store. Locked, Disabled and Suspended modules still give back (section 3.3).
     */
    public function refundsFor(string $key): ?CheckoutPaymentRefunds
    {
        foreach ($this->paymentMethods as [$class, $featureKey]) {
            $method = $this->container->make($class);

            if (! $method instanceof CheckoutPaymentMethod || ! $method->handles($key)) {
                continue;
            }

            $isReachable = $featureKey === null || $this->features()->state($featureKey) !== FeatureState::Unavailable;

            return $isReachable && $method instanceof CheckoutPaymentRefunds ? $method : null;
        }

        return null;
    }

    /**
     * @param  class-string<CheckoutPaymentMethod>  $class
     */
    private function paymentMethod(string $class): CheckoutPaymentMethod
    {
        $method = $this->container->make($class);

        return $method instanceof CheckoutPaymentMethod ? $method : throw new LogicException("{$class} is not a checkout payment method.");
    }

    /**
     * The checkout validators the current store must meet.
     *
     * @return list<CheckoutValidator>
     */
    public function validators(): array
    {
        $validators = [];

        foreach ($this->validators as [$class, $featureKey]) {
            if ($featureKey === null || $this->features()->isEnabled($featureKey)) {
                $validator = $this->container->make($class);
                $validators[] = $validator instanceof CheckoutValidator ? $validator : throw new LogicException("{$class} is not a checkout validator.");
            }
        }

        return $validators;
    }

    /**
     * Asked afresh each time: which modules a store may use can change while the app runs.
     */
    private function features(): Features
    {
        return $this->container->make(Features::class);
    }
}
