<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Http\Requests\ListStorefrontCurrenciesRequest;
use App\Tenant\Pricing\Http\Resources\StorefrontCurrencyResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The currencies customers can see prices and buy in.
 */
final class StorefrontCurrencyController extends Controller
{
    /**
     * List the currencies you can buy in.
     *
     * Open to anyone. The store's own currency first, then every other one
     * it offers now. A currency drops off while its exchange rate is too old,
     * and a request for it falls back to the store's own currency.
     */
    public function index(ListStorefrontCurrenciesRequest $request, FindExchangeRates $findExchangeRates): AnonymousResourceCollection
    {
        $offered = collect($findExchangeRates->offered());
        $page = new LengthAwarePaginator(
            $offered->forPage($request->page(), $request->perPage())->values()->map(static fn (AppliedExchangeRate $rate): StorefrontCurrencyResource => new StorefrontCurrencyResource($rate)),
            $offered->count(),
            $request->perPage(),
            $request->page(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return StorefrontCurrencyResource::collection($page);
    }
}
