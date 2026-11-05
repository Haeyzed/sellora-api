<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Features\Features;
use App\Shared\Features\FeatureState;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Contracts\RefundDestination;
use App\Tenant\Payments\Data\OfferedRefundDestination;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Where refunds can go (section 13): back to the original payment, core's own and the only one without modules, plus whatever destinations modules register from their service providers under their feature key.
 *
 * Offering is gated: a module's destination is offered only to the signed-in
 * customer whose order it is, only while the module is Enabled, and only when
 * the module's own rules allow it. Paying out is not: a destination already
 * chosen is reached whatever state its module is in, except Unavailable.
 */
final class RefundDestinations
{
    /** Core's own destination: back the way each payment came. */
    public const string ORIGINAL_PAYMENT = 'original_payment';

    /** Core's own destination for what a return credits toward its exchange's replacement order (Step 18c). Never offered: only an exchange refunds to it. */
    public const string EXCHANGE = 'exchange';

    /** What a destination's key looks like. */
    public const string KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    /** @var array<string, array{class-string<RefundDestination>, string}> Each module's destination class and feature key, by destination key. */
    private array $destinations = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Registers a module's destination. Modules register from their service providers, so a bad or clashing key stops the app at boot.
     *
     * @param  class-string<RefundDestination>  $destination
     * @param  string  $featureKey  The module's.
     *
     * @throws LogicException When its key is malformed, core's, or another destination's.
     */
    public function add(string $destination, string $featureKey): void
    {
        $key = $this->make($destination)->key();

        if (preg_match(self::KEY_PATTERN, $key) !== 1 || in_array($key, [self::ORIGINAL_PAYMENT, self::EXCHANGE], true)) {
            throw new LogicException("The refund destination {$destination} has the key \"{$key}\": keys are lowercase letters, digits and underscores, and \"".self::ORIGINAL_PAYMENT.'" and "'.self::EXCHANGE.'" are core\'s.');
        }

        if (isset($this->destinations[$key])) {
            throw new LogicException("The refund destinations {$this->destinations[$key][0]} and {$destination} both use the key \"{$key}\": each key must belong to one destination only.");
        }

        $this->destinations[$key] = [$destination, $featureKey];
    }

    /**
     * Where this order's refunds may go now, for the customer given (null for a guest, or anyone but the order's own customer): the original payment first, then each module's destination on offer, in the order they were registered.
     *
     * @return list<OfferedRefundDestination>
     */
    public function offeredFor(Order $order, ?Customer $customer): array
    {
        $offered = [new OfferedRefundDestination(self::ORIGINAL_PAYMENT, $this->originalPaymentLabel())];

        if ($customer === null || $order->customer_id !== $customer->id) {
            return $offered;
        }

        foreach ($this->destinations as [$class, $featureKey]) {
            $destination = $this->make($class);

            if ($this->features()->isEnabled($featureKey) && $destination->isOfferedFor($order, $customer)) {
                $offered[] = new OfferedRefundDestination($destination->key(), $destination->label());
            }
        }

        return $offered;
    }

    /**
     * Whether a destination is on offer now for this order and customer, as offeredFor() lists them.
     */
    public function isOffered(string $key, Order $order, ?Customer $customer): bool
    {
        foreach ($this->offeredFor($order, $customer) as $offered) {
            if ($offered->key === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a key belongs to a destination that can be paid out to, whatever its module's state: core's exchange destination, or one a module registered.
     */
    public function isRegistered(string $key): bool
    {
        return $key === self::EXCHANGE || isset($this->destinations[$key]);
    }

    /**
     * The destination to pay a refund out to: core's exchange destination, or a registered one whose module isn't Unavailable to the store (Locked, Disabled and Suspended modules still pay out, section 3.3). Null otherwise, or for a key no module registered.
     */
    public function forPayout(string $key): ?RefundDestination
    {
        if ($key === self::EXCHANGE) {
            return $this->make(ExchangeRefundDestination::class);
        }

        [$class, $featureKey] = $this->destinations[$key] ?? [null, null];

        if ($class === null || $featureKey === null || $this->features()->state($featureKey) === FeatureState::Unavailable) {
            return null;
        }

        return $this->make($class);
    }

    /**
     * What a destination is called in the current language: core's own, a registered module's, or else its key, for a destination no module registers any more.
     */
    public function labelOf(string $key): string
    {
        if ($key === self::ORIGINAL_PAYMENT) {
            return $this->originalPaymentLabel();
        }

        if ($key === self::EXCHANGE) {
            return $this->make(ExchangeRefundDestination::class)->label();
        }

        $class = $this->destinations[$key][0] ?? null;

        return $class === null ? $key : $this->make($class)->label();
    }

    private function originalPaymentLabel(): string
    {
        $label = __('refunds.destinations.'.self::ORIGINAL_PAYMENT);

        return is_string($label) ? $label : self::ORIGINAL_PAYMENT;
    }

    /**
     * @param  class-string<RefundDestination>  $class
     */
    private function make(string $class): RefundDestination
    {
        $destination = $this->container->make($class);

        return $destination instanceof RefundDestination ? $destination : throw new LogicException("{$class} is not a refund destination.");
    }

    /**
     * Asked afresh each time: which modules a store may use can change while the app runs.
     */
    private function features(): Features
    {
        return $this->container->make(Features::class);
    }
}
