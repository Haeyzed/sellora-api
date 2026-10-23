<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Exceptions\SecretInUrlException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses any request carrying a secret in its query string, before anything reads it: tokens, passwords, codes and credentials travel only in headers or the request body, never in a URL that servers, proxies and browsers log.
 *
 * Laravel's input and validation read the query string as well as the body,
 * so without this a token sent as ?token= would be accepted. "code" and
 * "state" are one-time codes only in requests that change something; the
 * GET pages a provider or gateway sends the browser back to (the social
 * sign-in relay, a payment return) receive theirs as the provider requires.
 */
final class RejectSecretsInUrl
{
    /** Never in a query string. */
    private const array SECRETS = [
        'token', 'access_token', 'refresh_token', 'cart_token', 'order_token', 'ticket',
        'password', 'current_password', 'new_password', 'password_confirmation', 'recovery_code',
        'secret', 'secret_key', 'client_secret', 'api_key', 'credentials',
        // The headers secrets travel in, copied into the URL instead.
        'authorization', 'x-cart-token', 'x-order-token',
    ];

    /** Never in the query string of a request that changes something. */
    private const array WRITE_SECRETS = ['code', 'state'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $refused = $request->isMethodSafe() ? self::SECRETS : [...self::SECRETS, ...self::WRITE_SECRETS];

        foreach ($request->query->keys() as $key) {
            if (in_array(mb_strtolower((string) $key), $refused, true)) {
                throw new SecretInUrlException;
            }
        }

        return $next($request);
    }
}
