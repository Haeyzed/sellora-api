<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Middleware;

use App\Shared\Auth\Contracts\TwoFactorRequirement;
use App\Shared\Auth\Exceptions\TwoFactorSetupRequiredException;
use App\Shared\Auth\Http\SignedInAccount;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets an account without two-factor authentication through only while nothing requires it, for guards where it is a setting (staff).
 *
 * When the store requires it, a staff member who hasn't set it up can only
 * see their profile, set it up and sign out, exactly like a platform admin
 * (EnsureTwoFactorIsEnabled). Runs after the auth middleware.
 */
final readonly class EnsureTwoFactorWhenRequired
{
    public function __construct(
        private SignedInAccount $signedInAccount,
        private TwoFactorRequirement $twoFactorRequirement,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws TwoFactorSetupRequiredException When two-factor authentication is required and the account hasn't set it up.
     * @throws LogicException When the route isn't behind an auth:<guard> middleware, which is a routing mistake.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $this->signedInAccount->from($request);

        if ($account instanceof TwoFactorAuthenticatable
            && ! $account->hasTwoFactorEnabled()
            && $this->twoFactorRequirement->isRequiredFor($account)) {
            throw new TwoFactorSetupRequiredException;
        }

        return $next($request);
    }
}
