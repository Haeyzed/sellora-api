<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Controllers;

use App\Shared\Http\Controller;
use App\Shared\Money\PricedRecordsRegistry;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Actions\UpdateStoreSettings;
use App\Tenant\Settings\Exceptions\PricingSettingsLockedException;
use App\Tenant\Settings\Http\Requests\UpdateStoreSettingsRequest;
use App\Tenant\Settings\Http\Requests\ViewStoreSettingsRequest;
use App\Tenant\Settings\Http\Resources\StoreSettingsResource;

/**
 * The store's core settings: name, country, currency, languages, tax mode, display units and contact details.
 */
final class StoreSettingsController extends Controller
{
    /**
     * Show the store's settings.
     *
     * Needs the settings.view permission.
     */
    public function show(ViewStoreSettingsRequest $request, FindStoreSettings $findStoreSettings, PricedRecordsRegistry $pricedRecordsRegistry): StoreSettingsResource
    {
        return new StoreSettingsResource($findStoreSettings->handle()->load('media'), $pricedRecordsRegistry->anyExist());
    }

    /**
     * Change the store's settings.
     *
     * Needs the settings.manage permission. Send only the settings that
     * change. The base currency and tax mode can't change once anything in
     * the store is priced. Changing the country doesn't change the currency,
     * timezone or tax mode.
     *
     * @throws PricingSettingsLockedException When the base currency or tax mode would change while something is priced.
     */
    public function update(UpdateStoreSettingsRequest $request, UpdateStoreSettings $updateStoreSettings, PricedRecordsRegistry $pricedRecordsRegistry): StoreSettingsResource
    {
        return new StoreSettingsResource($updateStoreSettings->handle($request->changes())->load('media'), $pricedRecordsRegistry->anyExist());
    }
}
