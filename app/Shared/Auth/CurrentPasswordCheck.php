<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Tenancy\TenantScopedKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;

/**
 * Confirms a signed-in person's current password before a sensitive change (new password, two-factor settings).
 *
 * Wrong passwords are counted per account and pause after too many, so a
 * stolen token can't be used to guess the password behind it.
 */
final readonly class CurrentPasswordCheck
{
    public function __construct(
        private Hasher $hasher,
        private AttemptLockout $attemptLockout,
    ) {}

    /**
     * @throws TooManyIncorrectAttemptsException After too many wrong passwords for this account.
     * @throws IncorrectCurrentPasswordException When the password is wrong.
     */
    public function ensureCorrect(Model&Authenticatable $account, string $currentPassword): void
    {
        $lockoutKey = TenantScopedKey::make('current-password-lockout', $account->getMorphClass(), (string) $account->getKey());
        $secondsUntilUnlocked = $this->attemptLockout->secondsUntilUnlocked($lockoutKey);

        if ($secondsUntilUnlocked !== null) {
            throw new TooManyIncorrectAttemptsException($secondsUntilUnlocked);
        }

        if (! $this->hasher->check($currentPassword, $account->getAuthPassword())) {
            $this->attemptLockout->recordFailure($lockoutKey);

            throw new IncorrectCurrentPasswordException;
        }

        $this->attemptLockout->clear($lockoutKey);
    }
}
