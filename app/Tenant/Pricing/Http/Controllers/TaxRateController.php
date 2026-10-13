<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Pricing\Actions\CreateTaxRate;
use App\Tenant\Pricing\Actions\DeleteTaxRate;
use App\Tenant\Pricing\Actions\UpdateTaxRate;
use App\Tenant\Pricing\Exceptions\TaxRateExistsException;
use App\Tenant\Pricing\Http\Requests\ListTaxRatesRequest;
use App\Tenant\Pricing\Http\Requests\ManagePricingRequest;
use App\Tenant\Pricing\Http\Requests\StoreTaxRateRequest;
use App\Tenant\Pricing\Http\Requests\UpdateTaxRateRequest;
use App\Tenant\Pricing\Http\Requests\ViewPricingRequest;
use App\Tenant\Pricing\Http\Resources\TaxRateResource;
use App\Tenant\Pricing\Models\TaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's tax rates, per tax class and region. No rates are set up for a new store: the merchant enters their own.
 */
final class TaxRateController extends Controller
{
    /**
     * List tax rates.
     *
     * Needs the pricing.view permission. Sorted by country, then state
     * (country-wide first), then class.
     */
    public function index(ListTaxRatesRequest $request): AnonymousResourceCollection
    {
        $taxRates = TaxRate::query()
            ->with('taxClass')
            ->when($request->countryCode() !== null, static fn ($query) => $query->where('country_code', $request->countryCode()))
            ->when($request->taxClassId() !== null, static fn ($query) => $query->where('tax_class_id', $request->taxClassId()))
            ->orderBy('country_code')
            ->orderByRaw('state_code nulls first')
            ->orderBy('tax_class_id')
            ->orderBy('id')
            ->paginate($request->perPage());

        return TaxRateResource::collection($taxRates);
    }

    /**
     * Add a tax rate.
     *
     * Needs the pricing.manage permission. One rate per class and region
     * (409 tax_rate_exists otherwise); a state's rate replaces the
     * country-wide rate there. Audited.
     *
     * @throws TaxRateExistsException
     */
    public function store(StoreTaxRateRequest $request, CreateTaxRate $createTaxRate): JsonResponse
    {
        $taxRate = $createTaxRate->handle($request->taxClass(), $request->countryCode(), $request->stateCode(), $request->rateName(), $request->rate());

        return (new TaxRateResource($taxRate->load('taxClass')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a tax rate.
     *
     * Needs the pricing.view permission.
     */
    public function show(ViewPricingRequest $request, TaxRate $taxRate): TaxRateResource
    {
        return new TaxRateResource($taxRate->load('taxClass'));
    }

    /**
     * Change a tax rate.
     *
     * Needs the pricing.manage permission. Its name or percentage; orders
     * already placed keep what they were charged. Audited.
     */
    public function update(UpdateTaxRateRequest $request, TaxRate $taxRate, UpdateTaxRate $updateTaxRate): TaxRateResource
    {
        return new TaxRateResource($updateTaxRate->handle($taxRate, $request->rateName(), $request->rate())->load('taxClass'));
    }

    /**
     * Delete a tax rate.
     *
     * Needs the pricing.manage permission. Audited.
     */
    public function destroy(ManagePricingRequest $request, TaxRate $taxRate, DeleteTaxRate $deleteTaxRate): Response
    {
        $deleteTaxRate->handle($taxRate);

        return response()->noContent();
    }
}
