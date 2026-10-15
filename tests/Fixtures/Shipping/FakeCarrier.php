<?php

declare(strict_types=1);

namespace Tests\Fixtures\Shipping;

use App\Shared\Money\Money;
use App\Tenant\Shipping\Contracts\ShippingCarrier;
use App\Tenant\Shipping\Data\CarrierQuoteRequest;
use App\Tenant\Shipping\Data\CarrierRate;
use App\Tenant\Shipping\Exceptions\CarrierUnavailableException;

/**
 * A carrier for tests: answers with the rates it is given, fails, or takes too long, and remembers what it was asked.
 */
final class FakeCarrier implements ShippingCarrier
{
    public const string FEATURE_KEY = 'test_carrier';

    /**
     * @var array<string, array{int, string}> Service code => [minor amount, currency].
     */
    public static array $rates = [];

    public static bool $fails = false;

    /** Seconds to wait before answering, to be later than the timeout. */
    public static int $delaySeconds = 0;

    /**
     * @var list<CarrierQuoteRequest>
     */
    public static array $asked = [];

    public static function reset(): void
    {
        self::$rates = [];
        self::$fails = false;
        self::$delaySeconds = 0;
        self::$asked = [];
    }

    public function key(): string
    {
        return 'fake_carrier';
    }

    public function name(): string
    {
        return 'Fake Carrier';
    }

    public function services(): array
    {
        return ['standard' => 'Standard', 'express' => 'Express'];
    }

    public function quote(CarrierQuoteRequest $request, int $timeoutSeconds): array
    {
        self::$asked[] = $request;

        if (self::$delaySeconds > 0) {
            sleep(self::$delaySeconds);
        }

        if (self::$fails) {
            throw new CarrierUnavailableException('The carrier is down.');
        }

        return array_values(array_map(static fn (string $service, array $rate): CarrierRate => new CarrierRate($service, Money::ofMinor($rate[0], $rate[1])), array_keys(self::$rates), self::$rates));
    }
}
