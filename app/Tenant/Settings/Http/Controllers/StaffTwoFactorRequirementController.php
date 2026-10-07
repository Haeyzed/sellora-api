<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Controllers;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Http\Controller;
use App\Shared\Money\PricedRecordsRegistry;
use App\Tenant\Settings\Actions\ChangeStaffTwoFactorRequirement;
use App\Tenant\Settings\Exceptions\OwnTwoFactorRequiredException;
use App\Tenant\Settings\Http\Requests\ChangeStaffTwoFactorRequirementRequest;
use App\Tenant\Settings\Http\Resources\StoreSettingsResource;

/**
 * Whether every staff member must use two-factor authentication. The owner's decision alone.
 */
final class StaffTwoFactorRequirementController extends Controller
{
    /**
     * Require two-factor authentication for staff, or stop requiring it.
     *
     * Only the store's owner, with their current password plus a code when
     * they use two-factor authentication; to require it, they must use it
     * themselves. While it is required, staff who haven't set it up can only
     * see their profile, set it up and sign out.
     *
     * @throws IncorrectCurrentPasswordException When the current password is wrong.
     * @throws InvalidTwoFactorCodeException When you use two-factor authentication and the code is missing, wrong or used.
     * @throws TooManyIncorrectAttemptsException After too many wrong passwords or codes.
     * @throws OwnTwoFactorRequiredException When requiring it while you don't use it yourself.
     */
    public function update(ChangeStaffTwoFactorRequirementRequest $request, ChangeStaffTwoFactorRequirement $changeStaffTwoFactorRequirement, PricedRecordsRegistry $pricedRecordsRegistry): StoreSettingsResource
    {
        $settings = $changeStaffTwoFactorRequirement->handle($request->actor(), $request->isRequired(), $request->currentPassword(), $request->code(), $request->recoveryCode());

        return new StoreSettingsResource($settings, $pricedRecordsRegistry->anyExist());
    }
}
