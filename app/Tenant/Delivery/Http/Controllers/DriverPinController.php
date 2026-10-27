<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Auth\AccessTokenResource;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Http\Controller;
use App\Tenant\Delivery\Actions\ChangeDriverPin;
use App\Tenant\Delivery\Actions\ChooseDriverPin;
use App\Tenant\Delivery\Exceptions\InvalidDriverSetupCodeException;
use App\Tenant\Delivery\Http\Requests\ChangeDriverPinRequest;
use App\Tenant\Delivery\Http\Requests\ChooseDriverPinRequest;
use Illuminate\Http\Response;

/**
 * A driver's own PIN: choosing it with a setup code from staff, and changing it.
 */
final class DriverPinController extends Controller
{
    /**
     * Choose a PIN with a setup code.
     *
     * For a new driver, or one whose PIN staff reset. Takes the driver's
     * phone, the 8-digit code staff gave them and a new 6-digit PIN, and
     * signs them in: returns a bearer token, and signs out any other device.
     * The code works once; wrong, used and expired codes give the same error,
     * and 5 wrong tries void it.
     *
     * @unauthenticated
     *
     * @throws InvalidDriverSetupCodeException
     * @throws AccountDeactivatedException
     */
    public function store(ChooseDriverPinRequest $request, ChooseDriverPin $chooseDriverPin): AccessTokenResource
    {
        return new AccessTokenResource($chooseDriverPin->handle(
            $request->e164Phone(),
            $request->string('setup_code')->value(),
            $request->string('pin')->value(),
            $request->deviceName(),
        ));
    }

    /**
     * Change your PIN.
     *
     * With your current PIN. Signs out your other devices. After 5 wrong
     * current PINs, changing pauses for 15 minutes.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function update(ChangeDriverPinRequest $request, ChangeDriverPin $changeDriverPin): Response
    {
        $changeDriverPin->handle($request->driver(), $request->string('current_pin')->value(), $request->string('pin')->value());

        return response()->noContent();
    }
}
