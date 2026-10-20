<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Tenant\Payments\Enums\PaymentMethod;
use Carbon\CarbonImmutable;

/**
 * A payment method paid by hand as the store has it set up, or as config/payments.php has it until the store does.
 *
 * Checkout (Step 16) offers the enabled ones, places the order with
 * paymentDueAt() as its deadline (and, for cash on delivery, as paid on
 * delivery), then starts the payment with StartManualPayment.
 */
final readonly class ManualPaymentMethod
{
    /**
     * @param  int|null  $collectionAlertHours  Cash on delivery only: how long after delivery staff are alerted that cash wasn't recorded.
     * @param  string|null  $instructions  What the buyer is told, such as the account to pay into.
     */
    public function __construct(
        public PaymentMethod $method,
        public bool $enabled,
        public int $deadlineHours,
        public ?int $collectionAlertHours,
        public ?string $instructions,
    ) {}

    /**
     * The deadline of an order placed now with this method.
     */
    public function paymentDueAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours($this->deadlineHours);
    }

    /**
     * Whether an order paid this way is paid on delivery.
     */
    public function isPaidOnDelivery(): bool
    {
        return $this->method === PaymentMethod::CashOnDelivery;
    }
}
