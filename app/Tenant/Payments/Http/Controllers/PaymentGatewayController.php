<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Controllers;

use App\Shared\Features\Exceptions\FeatureNotEnabledException;
use App\Shared\Features\Features;
use App\Shared\Http\Controller;
use App\Tenant\Payments\Actions\ChangePaymentGatewayConnection;
use App\Tenant\Payments\Actions\CheckPaymentGatewayConnection;
use App\Tenant\Payments\Actions\ConnectPaymentGateway;
use App\Tenant\Payments\Actions\DisconnectPaymentGateway;
use App\Tenant\Payments\Exceptions\PaymentGatewayCredentialsInvalidException;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotConnectedException;
use App\Tenant\Payments\Exceptions\PaymentGatewayUnreachableException;
use App\Tenant\Payments\Exceptions\UnknownPaymentGatewayException;
use App\Tenant\Payments\Http\Requests\ChangePaymentGatewayConnectionRequest;
use App\Tenant\Payments\Http\Requests\ConnectPaymentGatewayRequest;
use App\Tenant\Payments\Http\Requests\ManagePaymentGatewaysRequest;
use App\Tenant\Payments\Http\Requests\ViewPaymentGatewaysRequest;
use App\Tenant\Payments\Http\Resources\PaymentGatewayConnectionResource;
use App\Tenant\Payments\Http\Resources\PaymentGatewayResource;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\PaymentGatewayRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The store's own accounts with payment gateways: connecting, replacing credentials, turning them on or off for new payments, and disconnecting.
 */
final class PaymentGatewayController extends Controller
{
    /**
     * List payment gateways.
     *
     * Needs the payments.view permission. Every gateway the platform offers,
     * whether the store's plan includes it, and how the store is connected.
     */
    public function index(ViewPaymentGatewaysRequest $request, PaymentGatewayRegistry $registry, Features $features): AnonymousResourceCollection
    {
        $connections = PaymentGatewayConnection::query()->get()->keyBy('gateway');
        $gateways = array_map(static fn (string $key): PaymentGatewayResource => new PaymentGatewayResource($registry->get($key), $features->state($key), $connections->get($key)), $registry->keys());

        return PaymentGatewayResource::collection($gateways);
    }

    /**
     * Connect a payment gateway.
     *
     * Needs the payments.manage_gateways permission. Connects the store's own
     * account, or replaces its credentials, once the gateway confirms they
     * work. Connecting needs the gateway on the store's plan; replacing the
     * credentials of one already connected is always allowed. Credentials are
     * never shown again.
     *
     * @throws UnknownPaymentGatewayException
     * @throws FeatureNotEnabledException
     * @throws PaymentGatewayCredentialsInvalidException
     * @throws PaymentGatewayUnreachableException
     */
    public function connect(ConnectPaymentGatewayRequest $request, ConnectPaymentGateway $connectPaymentGateway): PaymentGatewayConnectionResource
    {
        return new PaymentGatewayConnectionResource($connectPaymentGateway->handle($request->actor(), $request->gatewayKey(), $request->credentials()));
    }

    /**
     * Change a payment gateway's connection.
     *
     * Needs the payments.manage_gateways permission. Turns it on or off for
     * new payments, or moves it in the order gateways are offered. Always
     * allowed: payments already under way carry on.
     *
     * @throws PaymentGatewayNotConnectedException
     */
    public function update(ChangePaymentGatewayConnectionRequest $request, string $gateway, ChangePaymentGatewayConnection $changePaymentGatewayConnection): PaymentGatewayConnectionResource
    {
        return new PaymentGatewayConnectionResource($changePaymentGatewayConnection->handle($request->actor(), $gateway, $request->enabled(), $request->priority()));
    }

    /**
     * Disconnect a payment gateway.
     *
     * Needs the payments.manage_gateways permission. Removes its credentials.
     *
     * @throws PaymentGatewayNotConnectedException
     */
    public function disconnect(ManagePaymentGatewaysRequest $request, string $gateway, DisconnectPaymentGateway $disconnectPaymentGateway): PaymentGatewayConnectionResource
    {
        return new PaymentGatewayConnectionResource($disconnectPaymentGateway->handle($request->actor(), $gateway));
    }

    /**
     * Check a payment gateway's credentials.
     *
     * Needs the payments.manage_gateways permission. Asks the gateway whether
     * the saved credentials still work.
     *
     * @throws PaymentGatewayNotConnectedException
     * @throws PaymentGatewayUnreachableException
     */
    public function check(ManagePaymentGatewaysRequest $request, string $gateway, CheckPaymentGatewayConnection $checkPaymentGatewayConnection): JsonResponse
    {
        $check = $checkPaymentGatewayConnection->handle($gateway);

        return response()->json(['data' => [
            /** Whether the gateway accepted the credentials. */
            'valid' => $check->isValid,
            /** "test" or "live". */
            'mode' => $check->mode->value,
        ]]);
    }
}
