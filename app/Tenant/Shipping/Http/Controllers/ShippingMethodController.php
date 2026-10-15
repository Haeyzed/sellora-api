<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Shipping\Actions\ArchiveShippingMethod;
use App\Tenant\Shipping\Actions\SaveShippingMethod;
use App\Tenant\Shipping\Exceptions\ShippingMethodArchivedException;
use App\Tenant\Shipping\Exceptions\ShippingRatesOverlapException;
use App\Tenant\Shipping\Http\Requests\ListShippingMethodsRequest;
use App\Tenant\Shipping\Http\Requests\ManageShippingRequest;
use App\Tenant\Shipping\Http\Requests\SaveShippingMethodRequest;
use App\Tenant\Shipping\Http\Requests\ViewShippingRequest;
use App\Tenant\Shipping\Http\Resources\ShippingMethodResource;
use App\Tenant\Shipping\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's shipping methods and their prices: shipped or delivered within a zone, or picked up at one of its locations.
 *
 * A method costs what its bracket for the order's weight or value says
 * (or its one flat price), in the base currency, with optional exact amounts
 * in other currencies; nothing once the lines that ship are worth its "free
 * over" threshold. Methods are archived, never deleted.
 */
final class ShippingMethodController extends Controller
{
    /**
     * List shipping methods.
     *
     * Needs the shipping.view permission. Sorted by zone, then position.
     * Archived methods only with archived=true.
     */
    public function index(ListShippingMethodsRequest $request): AnonymousResourceCollection
    {
        $methods = ShippingMethod::query()
            ->with(['zone', 'stockLocation', 'rates.currencyAmounts', 'currencyAmounts'])
            ->when($request->zoneId() !== null, static fn ($query) => $query->where('shipping_zone_id', $request->zoneId()))
            ->when($request->fulfilment() !== null, static fn ($query) => $query->where('fulfilment', $request->fulfilment()))
            ->when($request->archived(), static fn ($query) => $query->whereNotNull('archived_at'), static fn ($query) => $query->whereNull('archived_at'))
            ->orderByRaw('shipping_zone_id nulls last')
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($request->perPage());

        return ShippingMethodResource::collection($methods);
    }

    /**
     * Add a shipping method.
     *
     * Needs the shipping.manage permission. With its brackets, which never
     * overlap; a gap means it isn't offered there, and the response lists
     * gaps under "warnings". Audited.
     *
     * @throws ShippingRatesOverlapException
     */
    public function store(SaveShippingMethodRequest $request, SaveShippingMethod $saveShippingMethod): JsonResponse
    {
        $method = $saveShippingMethod->create($request->details());

        return (new ShippingMethodResource(self::loaded($method)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a shipping method.
     *
     * Needs the shipping.view permission.
     */
    public function show(ViewShippingRequest $request, ShippingMethod $method): ShippingMethodResource
    {
        return new ShippingMethodResource(self::loaded($method));
    }

    /**
     * Change a shipping method.
     *
     * Needs the shipping.manage permission. Send only what changes; "rates"
     * and "currency_amounts" replace every one set before. Orders already
     * placed keep what they paid. An archived method can't change (409
     * shipping_method_archived). Audited, with changes to its rates as one
     * change.
     *
     * @throws ShippingMethodArchivedException
     * @throws ShippingRatesOverlapException
     */
    public function update(SaveShippingMethodRequest $request, ShippingMethod $method, SaveShippingMethod $saveShippingMethod): ShippingMethodResource
    {
        return new ShippingMethodResource(self::loaded($saveShippingMethod->update($method, $request->details())));
    }

    /**
     * Archive a shipping method.
     *
     * Needs the shipping.manage permission. Shoppers stop being offered it,
     * for good; orders keep it as they chose it. Archiving twice changes
     * nothing. Audited.
     */
    public function archive(ManageShippingRequest $request, ShippingMethod $method, ArchiveShippingMethod $archiveShippingMethod): ShippingMethodResource
    {
        return new ShippingMethodResource(self::loaded($archiveShippingMethod->handle($method)));
    }

    private static function loaded(ShippingMethod $method): ShippingMethod
    {
        return $method->load(['zone', 'stockLocation', 'rates.currencyAmounts', 'currencyAmounts']);
    }
}
