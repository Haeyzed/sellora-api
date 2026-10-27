<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Delivery\Actions\CreateDriver;
use App\Tenant\Delivery\Actions\DeactivateDriver;
use App\Tenant\Delivery\Actions\IssueDriverSetupCode;
use App\Tenant\Delivery\Actions\ReactivateDriver;
use App\Tenant\Delivery\Actions\UpdateDriver;
use App\Tenant\Delivery\Exceptions\DriverDeactivatedException;
use App\Tenant\Delivery\Exceptions\DriverErasedException;
use App\Tenant\Delivery\Exceptions\DriverHoldsGoodsException;
use App\Tenant\Delivery\Exceptions\DriverPhoneTakenException;
use App\Tenant\Delivery\Http\Requests\ListDriversRequest;
use App\Tenant\Delivery\Http\Requests\ManageDriverRequest;
use App\Tenant\Delivery\Http\Requests\SaveDriverRequest;
use App\Tenant\Delivery\Http\Requests\ViewDriverRequest;
use App\Tenant\Delivery\Http\Resources\DriverAccountResource;
use App\Tenant\Delivery\Http\Resources\IssuedDriverSetupCodeResource;
use App\Tenant\Delivery\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's own delivery drivers: adding them, changing them, deactivating them, and giving them PIN setup codes.
 *
 * Staff never set or know a driver's PIN. They give the driver a one-time
 * code, and the driver chooses their own PIN with it in the driver app.
 */
final class DriverController extends Controller
{
    /**
     * List drivers.
     *
     * Needs the delivery.view permission. Sorted by name; erased drivers last.
     */
    public function index(ListDriversRequest $request): AnonymousResourceCollection
    {
        $drivers = Driver::query()
            ->when($request->activeFilter() !== null, static fn ($query) => $query->where('is_active', $request->activeFilter()))
            ->orderByRaw('name is null')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage());

        return DriverAccountResource::collection($drivers);
    }

    /**
     * Add a driver.
     *
     * Needs the drivers.manage permission and room in the plan's driver limit
     * (active drivers count). The driver has no PIN yet: the response holds a
     * one-time setup code, shown only now, to give them. Valid for 72 hours.
     *
     * @throws UsageLimitReachedException
     * @throws DriverPhoneTakenException
     */
    public function store(SaveDriverRequest $request, CreateDriver $createDriver): JsonResponse
    {
        $issued = $createDriver->handle($request->actor(), (string) $request->driverName(), (string) $request->e164Phone());

        return (new IssuedDriverSetupCodeResource($issued))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a driver.
     *
     * Needs the delivery.view permission.
     */
    public function show(ViewDriverRequest $request, Driver $driver): DriverAccountResource
    {
        return new DriverAccountResource($driver);
    }

    /**
     * Change a driver.
     *
     * Needs the drivers.manage permission. A new phone number clears the
     * driver's PIN and signs them out everywhere; then issue a setup code.
     *
     * @throws DriverErasedException
     * @throws DriverPhoneTakenException
     */
    public function update(SaveDriverRequest $request, Driver $driver, UpdateDriver $updateDriver): DriverAccountResource
    {
        return new DriverAccountResource($updateDriver->handle($request->actor(), $driver, $request->driverName(), $request->e164Phone()));
    }

    /**
     * Deactivate a driver.
     *
     * Needs the drivers.manage permission. They are signed out everywhere at
     * once and can't sign in; their history is kept, and they no longer
     * count towards the driver limit. Refused while they have goods (a
     * delivery picked up, on the way, or failed and not yet resolved);
     * deliveries only assigned to them are taken back.
     *
     * @throws DriverHoldsGoodsException
     */
    public function deactivate(ManageDriverRequest $request, Driver $driver, DeactivateDriver $deactivateDriver): DriverAccountResource
    {
        return new DriverAccountResource($deactivateDriver->handle($request->actor(), $driver));
    }

    /**
     * Reactivate a driver.
     *
     * Needs the drivers.manage permission and room in the plan's driver limit.
     *
     * @throws DriverErasedException
     * @throws UsageLimitReachedException
     */
    public function reactivate(ManageDriverRequest $request, Driver $driver, ReactivateDriver $reactivateDriver): DriverAccountResource
    {
        return new DriverAccountResource($reactivateDriver->handle($request->actor(), $driver));
    }

    /**
     * Issue a PIN setup code.
     *
     * Needs the drivers.manage permission. For a driver who forgot their PIN
     * or lost their phone: at once clears their PIN, signs them out
     * everywhere and stops any earlier code working. The new code is shown
     * only now; give it to the driver, who chooses a new PIN with it. Valid
     * for 72 hours, and voided after 5 wrong tries. Recorded in the activity
     * log.
     *
     * @throws DriverErasedException
     * @throws DriverDeactivatedException
     */
    public function issueSetupCode(ManageDriverRequest $request, Driver $driver, IssueDriverSetupCode $issueDriverSetupCode): JsonResponse
    {
        return (new IssuedDriverSetupCodeResource($issueDriverSetupCode->handle($request->actor(), $driver)))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
