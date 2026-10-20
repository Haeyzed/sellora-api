<?php

declare(strict_types=1);

namespace Tests\Fixtures\Payments;

use App\Tenant\Payments\Data\GatewayPaymentResult;
use App\Tenant\Payments\Data\GatewayRefundResult;

/**
 * What the fake payment gateway answers, set by each test, and what it was asked.
 */
final class FakeGatewayScript
{
    /** Whether every call times out, as if the gateway were down. */
    public bool $unavailable = false;

    /** Whether starting a payment is refused for good. */
    public bool $rejectsPayments = false;

    /**
     * The gateway account each secret key belongs to; keys not listed belong to "acct_main".
     *
     * @var array<string, string>
     */
    public array $accounts = [];

    /**
     * What verifying each payment reference answers; a reference not listed is still pending.
     *
     * @var array<string, GatewayPaymentResult>
     */
    public array $payments = [];

    /**
     * What refunding, then verifying, each refund reference answers; a refund not listed succeeds at once.
     *
     * @var array<string, GatewayRefundResult>
     */
    public array $refunds = [];

    /** What refunding answers for a refund reference not listed, and verifying it once it was sent; null succeeds at once, with the amount asked. */
    public ?GatewayRefundResult $refundAnswer = null;

    /** Whether refunding is refused for good. */
    public bool $rejectsRefunds = false;

    /** Whether it declares that it refunds part of a payment. */
    public bool $partialRefunds = true;

    /**
     * Each call, as [operation, reference].
     *
     * @var list<array{string, string}>
     */
    public array $calls = [];

    /**
     * How often an operation was called, for one reference or any.
     */
    public function timesCalled(string $operation, ?string $reference = null): int
    {
        return count(array_filter($this->calls, static fn (array $call): bool => $call[0] === $operation && ($reference === null || $call[1] === $reference)));
    }
}
