<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Actions;

use App\Tenant\Checkout\Exceptions\PaymentNotFoundException;
use App\Tenant\Payments\Actions\VerifyPayment;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Models\Payment;

/**
 * A buyer is back from a gateway's page (Step 16): asks the gateway, server-side, where their payment stands, and says only that.
 *
 * Coming back proves nothing, so nothing the buyer or the gateway's redirect
 * sent is believed: only VerifyPayment's own question to the gateway moves
 * the payment (and so the order). Found by the payment's reference alone, as
 * the buyer may be a guest whose cart is gone; the answer is the payment's
 * status and nothing more.
 */
final readonly class CheckReturnedPayment
{
    public function __construct(private VerifyPayment $verifyPayment) {}

    /**
     * @throws PaymentNotFoundException
     */
    public function handle(string $reference): PaymentStatus
    {
        $payment = Payment::query()->where('reference', $reference)->first() ?? throw new PaymentNotFoundException;

        return $this->verifyPayment->handle($payment)->status;
    }
}
