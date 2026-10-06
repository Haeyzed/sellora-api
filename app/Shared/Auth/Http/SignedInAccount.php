<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http;

use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Sanctum\Contracts\HasApiTokens;
use LogicException;

/**
 * The account behind a request on a route protected by `auth:<guard>`, and which guard signed it in.
 */
final readonly class SignedInAccount
{
    public function __construct(private AuthManager $authManager) {}

    /**
     * @throws LogicException When the route isn't protected by a token guard, which is a routing mistake.
     */
    public function from(Request $request): Model&Authenticatable&HasApiTokens
    {
        $account = $request->user();

        // Every guard's provider model is an Eloquent model with tokens, so only "no account" is left to rule out.
        if (! $account instanceof Model) {
            throw new LogicException('This route must be protected by an auth:<guard> middleware with a token-based account.');
        }

        return $account;
    }

    /**
     * The signed-in account, on routes for guards whose accounts can use two-factor authentication.
     *
     * @throws LogicException When the route's guard has no two-factor authentication, which is a routing mistake.
     */
    public function withTwoFactorFrom(Request $request): Model&Authenticatable&HasApiTokens&TwoFactorAuthenticatable
    {
        $account = $this->from($request);

        if (! $account instanceof TwoFactorAuthenticatable) {
            throw new LogicException('Two-factor routes may only be registered for guards whose accounts support two-factor authentication.');
        }

        return $account;
    }

    /**
     * The guard the `auth:<guard>` middleware signed the request in with, such as "staff".
     */
    public function guard(): string
    {
        return $this->authManager->getDefaultDriver();
    }
}
