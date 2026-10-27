<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Tenant\Delivery\Models\Driver;

/**
 * A signed-in driver changing their own PIN, confirming with the current one. Their other devices are signed out.
 *
 * Wrong current PINs pause after too many, so a stolen token can't be used to
 * guess the PIN behind it.
 */
final readonly class ChangeDriverPin
{
    public function __construct(
        private CurrentPasswordCheck $currentPasswordCheck,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @throws IncorrectCurrentPasswordException When the current PIN is wrong.
     * @throws TooManyIncorrectAttemptsException After too many wrong current PINs.
     */
    public function handle(Driver $driver, string $currentPin, string $newPin): void
    {
        $this->currentPasswordCheck->ensureCorrect($driver, $currentPin);

        $driver->forceFill(['pin' => $newPin])->save();
        $this->accessTokenIssuer->revokeAllExceptCurrent($driver);
    }
}
