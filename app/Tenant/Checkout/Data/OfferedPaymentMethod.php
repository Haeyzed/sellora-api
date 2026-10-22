<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Data;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Enums\PaymentMethodKind;
use Carbon\CarbonImmutable;

/**
 * A payment method offered for a checkout, as the shopper sees it in the quote.
 */
final readonly class OfferedPaymentMethod
{
    /**
     * @param  string  $key  Stable: "gateway:{gateway}", "bank_transfer", "cash_on_delivery", or a module's own, such as "gift_card".
     * @param  Money|null  $covers  A module's method: the most it covers of the order; null for core's, which pay what is left.
     * @param  bool  $isTest  Paid through a gateway in test mode: the order is then a test order, for good.
     * @param  bool  $payOnDelivery  Cash on delivery: the order may ship before it is paid.
     * @param  CarbonImmutable|null  $paymentDueAt  The order's payment deadline with this method; null for the usual one.
     * @param  string|null  $instructions  What the buyer is told, such as the account to pay into.
     */
    public function __construct(
        public string $key,
        public PaymentMethodKind $kind,
        public ?Money $covers = null,
        public bool $isTest = false,
        public bool $payOnDelivery = false,
        public ?CarbonImmutable $paymentDueAt = null,
        public ?string $instructions = null,
    ) {}
}
