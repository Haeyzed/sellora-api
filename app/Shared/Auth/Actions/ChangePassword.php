<?php

declare(strict_types=1);

namespace App\Shared\Auth\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\Contracts\HasApiTokens;

/**
 * Changes a signed-in person's password and signs them out on every other device, in case someone else had access.
 */
final readonly class ChangePassword
{
    public function __construct(
        private CurrentPasswordCheck $currentPasswordCheck,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @throws IncorrectCurrentPasswordException When the current password is wrong.
     * @throws TooManyIncorrectAttemptsException After too many wrong current passwords.
     */
    public function handle(Model&Authenticatable&HasApiTokens $account, string $currentPassword, string $newPassword): void
    {
        $this->currentPasswordCheck->ensureCorrect($account, $currentPassword);

        $account->forceFill([$account->getAuthPasswordName() => $newPassword])->save();
        $this->accessTokenIssuer->revokeAllExceptCurrent($account);
    }
}
