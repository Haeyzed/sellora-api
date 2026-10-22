<?php

declare(strict_types=1);

namespace App\Tenant\Checkout;

use App\Shared\Features\Features;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
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
     * @param  class-string<CheckoutPaymentMethod>  $method
     * @param  string|null  $featureKey  The module's; null for core's own.
     */
    public function addPaymentMethod(string $method, ?string $featureKey = null): void
    {
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
