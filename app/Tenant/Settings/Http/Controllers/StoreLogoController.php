<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Shared\Money\PricedRecordsRegistry;
use App\Tenant\Settings\Actions\ChangeStoreLogo;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Actions\RemoveStoreLogo;
use App\Tenant\Settings\Http\Requests\ChangeStoreLogoRequest;
use App\Tenant\Settings\Http\Resources\StoreSettingsResource;
use Illuminate\Http\Response;

/**
 * The store's logo, shown on its storefront.
 */
final class StoreLogoController extends Controller
{
    /**
     * Set the store's logo.
     *
     * Needs the settings.manage permission. Replaces the current logo. JPEG,
     * PNG or WebP, never SVG, up to 10 MB and 6000 × 6000 pixels; counts
     * towards the plan's storage.
     *
     * @throws UsageLimitReachedException
     */
    public function store(ChangeStoreLogoRequest $request, ChangeStoreLogo $changeStoreLogo, FindStoreSettings $findStoreSettings, PricedRecordsRegistry $pricedRecordsRegistry): StoreSettingsResource
    {
        $changeStoreLogo->handle($request->logo());

        return new StoreSettingsResource($findStoreSettings->handle()->load('media'), $pricedRecordsRegistry->anyExist());
    }

    /**
     * Remove the store's logo.
     *
     * Needs the settings.manage permission. Deletes the file too.
     */
    public function destroy(ChangeStoreLogoRequest $request, RemoveStoreLogo $removeStoreLogo): Response
    {
        $removeStoreLogo->handle();

        return response()->noContent();
    }
}
