<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Http\Controller;
use App\Shared\Privacy\ErasureOutcomeResource;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Tenant\Delivery\Actions\EraseDriver;
use App\Tenant\Delivery\Exceptions\DriverHoldsGoodsException;
use App\Tenant\Delivery\Http\Requests\DriverPrivacyRequest;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Privacy\DriverDataSubject;
use Illuminate\Http\JsonResponse;

/**
 * Staff answering a driver's data-protection request.
 */
final class DriverPrivacyController extends Controller
{
    /**
     * Get a driver's personal data.
     *
     * Needs the drivers.manage permission. Everything the store holds about
     * the driver, by section. Their PIN is never included.
     */
    public function show(DriverPrivacyRequest $request, Driver $driver, PersonalDataRegistry $personalDataRegistry): JsonResponse
    {
        return response()->json(['data' => $personalDataRegistry->export(DriverDataSubject::of($driver))]);
    }

    /**
     * Erase a driver.
     *
     * Needs the drivers.manage permission and your own password. Clears the
     * driver's name, phone and PIN, deactivates them and signs them out
     * everywhere; it can't be undone. Their past deliveries stay, without
     * their details. Refused while they have goods; deliveries only assigned
     * to them are taken back.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     * @throws DriverHoldsGoodsException
     */
    public function destroy(DriverPrivacyRequest $request, Driver $driver, EraseDriver $eraseDriver): ErasureOutcomeResource
    {
        return new ErasureOutcomeResource($eraseDriver->byStaff($request->actor(), $driver, $request->actorPassword()));
    }
}
