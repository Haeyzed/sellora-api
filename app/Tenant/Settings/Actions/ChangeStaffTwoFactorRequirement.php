<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Actions;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\IdentityConfirmation;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Exceptions\MissingStoreSettingsException;
use App\Tenant\Settings\Exceptions\OwnTwoFactorRequiredException;
use App\Tenant\Settings\Models\StoreSettings;

/**
 * The owner requires two-factor authentication for every staff member, or stops requiring it (section 10).
 *
 * Needs the owner's password, plus a code when they use two-factor
 * authentication; to require it, they must use it themselves. While it is
 * required, staff who haven't set it up can only see their profile, set it
 * up and sign out. The change is audited and goes in the store's activity
 * log.
 */
final readonly class ChangeStaffTwoFactorRequirement
{
    public function __construct(
        private IdentityConfirmation $identityConfirmation,
        private FindStoreSettings $findStoreSettings,
    ) {}

    /**
     * @throws IncorrectCurrentPasswordException When the current password is wrong.
     * @throws InvalidTwoFactorCodeException When the owner uses two-factor authentication and the code is missing, wrong or used.
     * @throws TooManyIncorrectAttemptsException After too many wrong passwords or codes.
     * @throws OwnTwoFactorRequiredException When requiring it while the owner doesn't use it.
     * @throws MissingStoreSettingsException When the store has no settings row, which is a bug.
     */
    public function handle(StaffMember $owner, bool $required, string $currentPassword, ?string $code, ?string $recoveryCode): StoreSettings
    {
        $this->identityConfirmation->ensureConfirmed($owner, $currentPassword, $code, $recoveryCode);

        if ($required && ! $owner->hasTwoFactorEnabled()) {
            throw new OwnTwoFactorRequiredException;
        }

        return StoreSettings::query()->getConnection()->transaction(function () use ($owner, $required): StoreSettings {
            $settings = $this->findStoreSettings->forUpdate();

            if ($settings->require_staff_two_factor === $required) {
                return $settings;
            }

            $settings->forceFill(['require_staff_two_factor' => $required])->save();

            activity('team')
                ->causedBy($owner)
                ->performedOn($settings)
                ->event($required ? 'staff_two_factor_required' : 'staff_two_factor_no_longer_required')
                ->log($required
                    ? "{$owner->name} required two-factor authentication for every staff member"
                    : "{$owner->name} stopped requiring two-factor authentication for staff");

            return $settings;
        });
    }
}
