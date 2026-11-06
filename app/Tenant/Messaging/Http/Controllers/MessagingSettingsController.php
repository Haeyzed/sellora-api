<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Messaging\Actions\ChangeMessagingSettings;
use App\Tenant\Messaging\Http\Requests\ChangeMessagingSettingsRequest;
use App\Tenant\Messaging\Http\Requests\ShowMessagingSettingsRequest;
use App\Tenant\Messaging\Http\Resources\MessagingSettingsResource;
use App\Tenant\Messaging\Models\MessagingSettings;

/**
 * Who the store's emails come from and where replies go, and whether staff are emailed about each new order, for staff.
 */
final class MessagingSettingsController extends Controller
{
    /**
     * Show the messaging settings.
     *
     * Needs the settings.view permission.
     */
    public function show(ShowMessagingSettingsRequest $request): MessagingSettingsResource
    {
        return new MessagingSettingsResource(MessagingSettings::current());
    }

    /**
     * Change the messaging settings.
     *
     * Needs the settings.manage permission. Send only what changes; null for
     * the sender name or reply-to address goes back to the store's own name
     * or contact email. Every email the store sends from then on uses them,
     * from the platform's address.
     */
    public function update(ChangeMessagingSettingsRequest $request, ChangeMessagingSettings $changeMessagingSettings): MessagingSettingsResource
    {
        return new MessagingSettingsResource($changeMessagingSettings->handle($request->changes()));
    }
}
