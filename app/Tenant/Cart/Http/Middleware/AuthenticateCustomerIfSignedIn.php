<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Middleware;

use App\Tenant\Customers\Models\Customer;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets guests and signed-in customers use the same cart routes: a request with a bearer token must be a valid customer sign-in, one without is a guest.
 *
 * A token that isn't a customer's (wrong, expired, revoked, or another
 * guard's) is refused with 401 rather than quietly treated as a guest, so a
 * shopper whose sign-in lapsed never sees an empty cart instead of theirs.
 */
final class AuthenticateCustomerIfSignedIn
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException When a bearer token is sent but isn't a customer's valid sign-in.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() === null) {
            return $next($request);
        }

        Auth::shouldUse(Customer::GUARD);

        if (! Auth::guard(Customer::GUARD)->user() instanceof Customer) {
            throw new AuthenticationException(guards: [Customer::GUARD]);
        }

        return $next($request);
    }
}
