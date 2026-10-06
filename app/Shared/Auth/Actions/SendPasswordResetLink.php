<?php

declare(strict_types=1);

namespace App\Shared\Auth\Actions;

use Illuminate\Contracts\Auth\PasswordBrokerFactory;

/**
 * Emails a link to choose a new password, if an active account uses the address.
 *
 * Always looks the same to the caller, whether or not the account exists or
 * a link was sent moments ago, so it can't be used to discover accounts.
 */
final readonly class SendPasswordResetLink
{
    public function __construct(private PasswordBrokerFactory $passwordBrokers) {}

    /**
     * @param  string  $broker  The password broker of the kind of account, such as "staff_members".
     */
    public function handle(string $broker, string $email): void
    {
        $this->passwordBrokers->broker($broker)->sendResetLink(['email' => $email, 'is_active' => true]);
    }
}
