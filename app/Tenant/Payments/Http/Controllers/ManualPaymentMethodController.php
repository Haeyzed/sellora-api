<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Payments\Actions\ChangeManualPaymentMethod;
use App\Tenant\Payments\Actions\FindManualPaymentMethods;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Http\Requests\ChangeManualPaymentMethodRequest;
use App\Tenant\Payments\Http\Requests\ViewPaymentGatewaysRequest;
use App\Tenant\Payments\Http\Resources\ManualPaymentMethodResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The payment methods paid by hand that every store has: bank transfer and cash on delivery. Not on any plan; each store turns them on.
 */
final class ManualPaymentMethodController extends Controller
{
    /**
     * List payment methods paid by hand.
     *
     * Needs the payments.view permission. Bank transfer and cash on delivery,
     * as the store has set them up; off until it turns them on.
     */
    public function index(ViewPaymentGatewaysRequest $request, FindManualPaymentMethods $findManualPaymentMethods): AnonymousResourceCollection
    {
        return ManualPaymentMethodResource::collection(array_map(static fn ($method): ManualPaymentMethodResource => new ManualPaymentMethodResource($method), $findManualPaymentMethods->all()));
    }

    /**
     * Set up a payment method paid by hand.
     *
     * Needs the payments.manage_gateways permission. Turns it on or off, sets
     * how long buyers have to pay (only for orders placed afterwards), and
     * what they are told.
     */
    public function update(ChangeManualPaymentMethodRequest $request, PaymentMethod $method, ChangeManualPaymentMethod $changeManualPaymentMethod): ManualPaymentMethodResource
    {
        return new ManualPaymentMethodResource($changeManualPaymentMethod->handle($request->actor(), $method, $request->changes()));
    }
}
