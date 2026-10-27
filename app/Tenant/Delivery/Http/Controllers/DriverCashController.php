<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Delivery\Actions\RecordCashHandover;
use App\Tenant\Delivery\Exceptions\CashHandoverExceedsBalanceException;
use App\Tenant\Delivery\Http\Requests\ListDriverCashEntriesRequest;
use App\Tenant\Delivery\Http\Requests\RecordCashHandoverRequest;
use App\Tenant\Delivery\Http\Requests\ViewDriverRequest;
use App\Tenant\Delivery\Http\Resources\DriverCashBalanceResource;
use App\Tenant\Delivery\Http\Resources\DriverCashEntryResource;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Services\DriverCashLedger;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The cash each driver holds: collected on deliveries paid in cash, less what they handed over to staff.
 *
 * Every movement is a ledger entry that never changes. Amounts are kept
 * per currency and never added together.
 */
final class DriverCashController extends Controller
{
    /**
     * Show the cash a driver holds.
     *
     * Needs the delivery.view permission. One amount per currency, in minor
     * units.
     */
    public function show(ViewDriverRequest $request, Driver $driver, DriverCashLedger $driverCashLedger): DriverCashBalanceResource
    {
        return new DriverCashBalanceResource($driver, $driverCashLedger->balances($driver));
    }

    /**
     * List a driver's cash entries.
     *
     * Needs the delivery.view permission. Newest first.
     */
    public function entries(ListDriverCashEntriesRequest $request, Driver $driver): AnonymousResourceCollection
    {
        return DriverCashEntryResource::collection($driver->cashEntries()->with('delivery')->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Record cash a driver handed over.
     *
     * Needs the delivery.cash.manage permission and an Idempotency-Key. It
     * comes off what the driver holds in that currency, and can never be more:
     * two handovers recorded at the same moment can't both take the same cash.
     *
     * @throws CashHandoverExceedsBalanceException
     */
    #[HeaderParameter('Idempotency-Key', 'A new key for each handover, 8 to 128 letters, digits, - or _. Sending the same request again with it returns the first answer instead of recording it twice.', required: true, type: 'string', example: 'handover-2f9c1a7e')]
    public function handover(RecordCashHandoverRequest $request, Driver $driver, RecordCashHandover $recordCashHandover): JsonResponse
    {
        $entry = $recordCashHandover->handle($request->actor(), $driver, $request->cash());

        return (new DriverCashEntryResource($entry))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
