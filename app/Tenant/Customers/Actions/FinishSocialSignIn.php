<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Tenant\Customers\Data\SocialSignInResult;
use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Enums\SocialSignInIntent;
use App\Tenant\Customers\Exceptions\InvalidSocialSignInException;
use App\Tenant\Customers\Exceptions\SocialSignInFailedException;
use App\Tenant\Customers\Services\SocialAccounts;
use App\Tenant\Customers\Services\SocialSignInAttempts;

/**
 * Finishes a customer's social sign-in with what the provider sent back through the relay page (Step 10b): signs them in, makes their account, or says what they still need to do.
 */
final readonly class FinishSocialSignIn
{
    public function __construct(
        private SocialSignInAttempts $socialSignInAttempts,
        private SocialAccounts $socialAccounts,
    ) {}

    /**
     * @throws InvalidSocialSignInException
     * @throws SocialSignInFailedException
     * @throws AccountDeactivatedException
     */
    public function handle(SocialProvider $provider, string $state, string $deviceName): SocialSignInResult
    {
        $attempt = $this->socialSignInAttempts->consume($provider, $state, SocialSignInIntent::SignIn);
        $profile = $this->socialSignInAttempts->profile($attempt);

        return $this->socialAccounts->signIn($profile, $deviceName)->withReturnPath($attempt->return_path);
    }
}
