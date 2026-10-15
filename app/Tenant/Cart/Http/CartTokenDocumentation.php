<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http;

use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Http\Middleware\AuthenticateCustomerIfSignedIn;
use App\Tenant\Customers\Models\Customer;
use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Documents how the cart endpoints are signed in to: a guest's cart token is its own security scheme (an API key in the X-Cart-Token header).
 *
 * Each cart endpoint takes a customer's sign-in or a cart token; creating a
 * cart takes a sign-in or nothing; merging takes both, the customer's
 * sign-in and the guest cart's token.
 */
final class CartTokenDocumentation implements OperationTransformer
{
    public const string SCHEME = 'cartToken';

    public static function scheme(): SecurityScheme
    {
        return SecurityScheme::apiKey('header', CartToken::HEADER)
            ->as(self::SCHEME)
            ->setDescription("A guest's cart token, given once in the response that created the cart. Ignored when signed in, except when merging.");
    }

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $middleware = $routeInfo->route->gatherMiddleware();

        if (! in_array(AuthenticateCustomerIfSignedIn::class, $middleware, true)) {
            return;
        }

        $signIn = SecurityScheme::http('bearer')->schemeName;

        $operation->security = match (true) {
            in_array('auth:'.Customer::GUARD, $middleware, true) => [new SecurityRequirement([$signIn => [], self::SCHEME => []])],
            $routeInfo->route->getName() === 'cart.store' => [new SecurityRequirement([$signIn => []]), new SecurityRequirement([])],
            default => [new SecurityRequirement([$signIn => []]), new SecurityRequirement([self::SCHEME => []])],
        };
    }
}
