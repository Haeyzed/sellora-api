<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Checkout\Actions\CheckReturnedPayment;
use App\Tenant\Checkout\Exceptions\PaymentNotFoundException;
use App\Tenant\Checkout\Http\Resources\PaymentStatusResource;

/**
 * A buyer back from a gateway's page (Step 16). Needs no sign-in: the payment's reference is enough, and the answer is only its status.
 */
final class PaymentReturnController extends Controller
{
    /**
     * Check a payment after the gateway.
     *
     * For the storefront page a gateway sends the buyer back to: asks the
     * gateway where the payment stands and returns only its status. Whatever
     * the gateway's redirect carried is never believed. Limited per payment,
     * under a ceiling per IP address.
     *
     * @throws PaymentNotFoundException
     */
    public function show(string $reference, CheckReturnedPayment $checkReturnedPayment): PaymentStatusResource
    {
        return new PaymentStatusResource($checkReturnedPayment->handle($reference));
    }
}
