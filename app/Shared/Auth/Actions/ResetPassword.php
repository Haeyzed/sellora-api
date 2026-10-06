<?php

declare(strict_types=1);

namespace App\Shared\Auth\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Exceptions\InvalidPasswordResetException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\Contracts\HasApiTokens;
use LogicException;

/**
 * Sets a new password from an emailed reset link, and signs the account out everywhere.
 */
final readonly class ResetPassword
{
    public function __construct(
        private PasswordBrokerFactory $passwordBrokers,
        private AccessTokenIssuer $accessTokenIssuer,
        private Dispatcher $events,
    ) {}

    /**
     * @param  string  $broker  The password broker of the kind of account, such as "customers".
     *
     * @throws InvalidPasswordResetException When the link is wrong, used or expired, or the account doesn't exist.
     */
    public function handle(string $broker, string $email, string $token, string $newPassword): void
    {
        $status = $this->passwordBrokers->broker($broker)->reset(
            ['email' => $email, 'token' => $token, 'password' => $newPassword, 'is_active' => true],
            function (CanResetPassword $account, string $password): void {
                if (! $account instanceof Model || ! $account instanceof Authenticatable || ! $account instanceof HasApiTokens) {
                    throw new LogicException($account::class.' cannot hold sign-in tokens.');
                }

                $account->forceFill(['password' => $password])->save();
                $this->accessTokenIssuer->revokeAll($account);
                $this->events->dispatch(new PasswordReset($account));
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw new InvalidPasswordResetException;
        }
    }
}
