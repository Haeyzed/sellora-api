<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Pricing\Actions\CreateTaxClass;
use App\Tenant\Pricing\Actions\DeleteTaxClass;
use App\Tenant\Pricing\Actions\UpdateTaxClass;
use App\Tenant\Pricing\Exceptions\TaxClassInUseException;
use App\Tenant\Pricing\Exceptions\TaxClassNameTakenException;
use App\Tenant\Pricing\Http\Requests\ListTaxClassesRequest;
use App\Tenant\Pricing\Http\Requests\ManagePricingRequest;
use App\Tenant\Pricing\Http\Requests\StoreTaxClassRequest;
use App\Tenant\Pricing\Http\Requests\UpdateTaxClassRequest;
use App\Tenant\Pricing\Http\Requests\ViewPricingRequest;
use App\Tenant\Pricing\Http\Resources\TaxClassResource;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's tax classes, such as "Standard", "Reduced", "Zero-rated" and "Exempt", which variants are put in.
 */
final class TaxClassController extends Controller
{
    /**
     * List tax classes.
     *
     * Needs the pricing.view permission. Sorted by name.
     */
    public function index(ListTaxClassesRequest $request): AnonymousResourceCollection
    {
        return TaxClassResource::collection(TaxClass::query()->orderBy('name')->orderBy('id')->paginate($request->perPage()));
    }

    /**
     * Add a tax class.
     *
     * Needs the pricing.manage permission. It then needs rates for the
     * countries the store sells to; without one, its lines are taxed at
     * nothing and marked "no rate". Audited.
     *
     * @throws TaxClassNameTakenException
     */
    public function store(StoreTaxClassRequest $request, CreateTaxClass $createTaxClass): JsonResponse
    {
        return (new TaxClassResource($createTaxClass->handle($request->taxClassName(), $request->isExempt())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a tax class.
     *
     * Needs the pricing.view permission.
     */
    public function show(ViewPricingRequest $request, TaxClass $taxClass): TaxClassResource
    {
        return new TaxClassResource($taxClass);
    }

    /**
     * Change a tax class.
     *
     * Needs the pricing.manage permission. Send only what changes. Making a
     * class the default, or the one shipping is taxed at, moves that from the
     * class that had it. Audited.
     *
     * @throws TaxClassNameTakenException
     */
    public function update(UpdateTaxClassRequest $request, TaxClass $taxClass, UpdateTaxClass $updateTaxClass): TaxClassResource
    {
        return new TaxClassResource($updateTaxClass->handle($taxClass, $request->changes()));
    }

    /**
     * Delete a tax class.
     *
     * Needs the pricing.manage permission. Only while nothing uses it: no
     * variant (even one in the trash), no rate, not the default and not used
     * for shipping; otherwise 409 tax_class_in_use. Audited.
     *
     * @throws TaxClassInUseException
     */
    public function destroy(ManagePricingRequest $request, TaxClass $taxClass, DeleteTaxClass $deleteTaxClass): Response
    {
        $deleteTaxClass->handle($taxClass);

        return response()->noContent();
    }
}
