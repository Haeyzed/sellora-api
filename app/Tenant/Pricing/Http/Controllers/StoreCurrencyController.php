<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Pricing\Actions\AddStoreCurrency;
use App\Tenant\Pricing\Actions\ChangeStoreCurrency;
use App\Tenant\Pricing\Exceptions\CurrencyIsBaseException;
use App\Tenant\Pricing\Exceptions\StoreCurrencyExistsException;
use App\Tenant\Pricing\Http\Requests\AddStoreCurrencyRequest;
use App\Tenant\Pricing\Http\Requests\ChangeStoreCurrencyRequest;
use App\Tenant\Pricing\Http\Requests\ListStoreCurrenciesRequest;
use App\Tenant\Pricing\Http\Requests\ManagePricingRequest;
use App\Tenant\Pricing\Http\Requests\SetExchangeRateRequest;
use App\Tenant\Pricing\Http\Requests\ViewPricingRequest;
use App\Tenant\Pricing\Http\Resources\StoreCurrencyResource;
use App\Tenant\Pricing\Models\StoreCurrency;
use App\Tenant\Pricing\Services\StoreCurrencyRates;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The currencies the store sells in besides its base currency, with their exchange rates (section 3.3).
 */
final class StoreCurrencyController extends Controller
{
    public function __construct(
        private readonly StoreCurrencyRates $storeCurrencyRates,
        private readonly FindStoreSettings $findStoreSettings,
    ) {}

    /**
     * List the store's currencies.
     *
     * Needs the pricing.view permission. Sorted by code. Each shows the
     * market rate beside the rate prices use, and whether customers can buy
     * in it now.
     */
    public function index(ListStoreCurrenciesRequest $request): AnonymousResourceCollection
    {
        $currencies = StoreCurrency::query()->orderBy('currency_code')->paginate($request->perPage());
        $statuses = $this->storeCurrencyRates->statusesOf($this->baseCurrency(), $currencies->items());

        return StoreCurrencyResource::collection($currencies->through(static fn (StoreCurrency $currency): StoreCurrencyResource => new StoreCurrencyResource($statuses[$currency->currency_code])));
    }

    /**
     * Add a currency.
     *
     * Needs the pricing.manage permission. Within the plan's limit of
     * presentment currencies (403 usage_limit_reached beyond it); the base
     * currency never counts and can't be added. Customers can buy in it once
     * it has a rate. Audited.
     *
     * @throws CurrencyIsBaseException
     * @throws StoreCurrencyExistsException
     * @throws UsageLimitReachedException
     */
    public function store(AddStoreCurrencyRequest $request, AddStoreCurrency $addStoreCurrency): JsonResponse
    {
        $currency = $addStoreCurrency->handle($request->currencyCode(), $request->marginPercent());

        return $this->resourceFor($currency)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show one of the store's currencies.
     *
     * Needs the pricing.view permission.
     */
    public function show(ViewPricingRequest $request, StoreCurrency $currency): StoreCurrencyResource
    {
        return $this->resourceFor($currency);
    }

    /**
     * Change one of the store's currencies.
     *
     * Needs the pricing.manage permission. Send only what changes:
     * whether it is active, and its margin on the market rate (0 to 25%).
     * Reactivating counts towards the plan's limit. Audited.
     *
     * @throws UsageLimitReachedException
     */
    public function update(ChangeStoreCurrencyRequest $request, StoreCurrency $currency, ChangeStoreCurrency $changeStoreCurrency): StoreCurrencyResource
    {
        return $this->resourceFor($changeStoreCurrency->handle($currency, $request->isActive(), $request->marginPercent()));
    }

    /**
     * Set a manual exchange rate.
     *
     * Needs the pricing.manage permission. Prices in this currency use it
     * instead of the market rate (and no margin) until it is removed; it never
     * goes stale. The market rate is still shown beside it. Audited.
     */
    public function setRate(SetExchangeRateRequest $request, StoreCurrency $currency, ChangeStoreCurrency $changeStoreCurrency): StoreCurrencyResource
    {
        return $this->resourceFor($changeStoreCurrency->setManualRate($currency, $request->rate()));
    }

    /**
     * Go back to the market rate.
     *
     * Needs the pricing.manage permission. Removes the manual rate. Audited.
     */
    public function clearRate(ManagePricingRequest $request, StoreCurrency $currency, ChangeStoreCurrency $changeStoreCurrency): StoreCurrencyResource
    {
        return $this->resourceFor($changeStoreCurrency->setManualRate($currency, null));
    }

    private function resourceFor(StoreCurrency $currency): StoreCurrencyResource
    {
        return new StoreCurrencyResource($this->storeCurrencyRates->statusesOf($this->baseCurrency(), [$currency])[$currency->currency_code]);
    }

    private function baseCurrency(): string
    {
        return $this->findStoreSettings->handle()->currency_code;
    }
}
