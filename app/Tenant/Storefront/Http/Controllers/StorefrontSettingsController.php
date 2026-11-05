<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Actions\ChangeStorefrontSettings;
use App\Tenant\Storefront\Http\Requests\ChangeStorefrontSettingsRequest;
use App\Tenant\Storefront\Http\Requests\ShowStorefrontSettingsRequest;
use App\Tenant\Storefront\Http\Resources\StorefrontSettingsResource;
use App\Tenant\Storefront\Models\StorefrontSettings;

/**
 * How the storefront presents itself to search engines and social sites, for staff.
 */
final class StorefrontSettingsController extends Controller
{
    /**
     * Show the storefront settings.
     *
     * Needs the storefront.view permission.
     */
    public function show(ShowStorefrontSettingsRequest $request): StorefrontSettingsResource
    {
        return new StorefrontSettingsResource(StorefrontSettings::current()->load('media'));
    }

    /**
     * Change the storefront settings.
     *
     * Needs the storefront.manage permission. Send only what changes; for
     * texts, only the languages sent change. Shoppers see the change on their
     * next visit.
     */
    public function update(ChangeStorefrontSettingsRequest $request, ChangeStorefrontSettings $changeStorefrontSettings): StorefrontSettingsResource
    {
        return new StorefrontSettingsResource($changeStorefrontSettings->handle($request->changes())->load('media'));
    }
}
