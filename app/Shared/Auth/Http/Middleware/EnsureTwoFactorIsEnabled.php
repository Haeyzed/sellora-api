<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Middleware;

use App\Shared\Auth\Exceptions\TwoFactorSetupRequiredException;
use App\Shared\Auth\Http\SignedInAccount;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only accounts with two-factor authentication on through, for guards where it is mandatory (platform admins).
 *
 * Until it is set up, such an account can only see its profile, set up
 * two-factor authentication and sign out. Runs after the auth middleware.
 */
final readonly class EnsureTwoFactorIsEnabled
{
    public function __construct(private SignedInAccount $signedInAccount) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws TwoFactorSetupRequiredException When the signed-in account hasn't set up two-factor authentication.
     * @throws LogicException When the route isn't behind an auth:<guard> middleware, which is a routing mistake.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $this->signedInAccount->from($request);

        if ($account instanceof TwoFactorAuthenticatable && ! $account->hasTwoFactorEnabled()) {
            throw new TwoFactorSetupRequiredException;
        }

        return $next($request);
    }
}
