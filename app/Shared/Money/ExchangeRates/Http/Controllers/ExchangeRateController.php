<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates\Http\Controllers;

use App\Shared\Http\Controller;
use App\Shared\Money\ExchangeRates\ExchangeRates;
use App\Shared\Money\ExchangeRates\Http\Requests\ListExchangeRatesRequest;
use App\Shared\Money\ExchangeRates\Http\Resources\ExchangeRateResource;
use App\Shared\Money\ExchangeRates\Models\ExchangeRate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The market exchange rates the platform has fetched for every store.
 */
final class ExchangeRateController extends Controller
{
    /**
     * List fetched exchange rates.
     *
     * Any platform admin. Newest fetch first, optionally one currency's
     * history. Rates are fetched hourly; when none has been fetched for 12
     * hours an error is reported, and stores stop offering currencies whose
     * rate is older than 48 hours.
     */
    public function index(ListExchangeRatesRequest $request, ExchangeRates $exchangeRates): AnonymousResourceCollection
    {
        $rates = ExchangeRate::query()
            ->when($request->currency() !== null, static fn ($query) => $query->where('currency', $request->currency()))
            // Rows are only ever added, in fetch order, so the newest have the highest IDs.
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage());

        return ExchangeRateResource::collection($rates)->additional(['last_fetched_at' => $exchangeRates->lastFetchedAt()?->toIso8601String()]);
    }
}
