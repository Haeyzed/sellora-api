<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Returns\Actions\ChangeReturnSettings;
use App\Tenant\Returns\Http\Requests\ChangeReturnSettingsRequest;
use App\Tenant\Returns\Http\Requests\ViewReturnSettingsRequest;
use App\Tenant\Returns\Http\Resources\ReturnSettingsResource;
use App\Tenant\Returns\Models\ReturnSettings;

/**
 * How the store takes returns: the window after delivery, whether buyers may ask themselves, and whether a photo is required for damage or the wrong item.
 */
final class ReturnSettingsController extends Controller
{
    /**
     * Show the return settings.
     *
     * Needs the returns.view permission. The defaults until staff first change them.
     */
    public function show(ViewReturnSettingsRequest $request): ReturnSettingsResource
    {
        return new ReturnSettingsResource(ReturnSettings::current());
    }

    /**
     * Change the return settings.
     *
     * Needs the returns.manage permission. Anything left out stays as it is.
     * A shorter window applies to requests made from now on, never to
     * returns already asked for.
     */
    public function update(ChangeReturnSettingsRequest $request, ChangeReturnSettings $changeReturnSettings): ReturnSettingsResource
    {
        return new ReturnSettingsResource($changeReturnSettings->handle($request->windowDays(), $request->customerRequests(), $request->photosRequired()));
    }
}
