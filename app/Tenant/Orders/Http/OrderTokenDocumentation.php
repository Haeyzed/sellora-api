<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http;

use App\Tenant\Orders\Models\OrderAccessToken;
use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Documents how a guest's order endpoints are reached: the order's access token is its own security scheme (an API key in the X-Order-Token header), never a sign-in.
 */
final class OrderTokenDocumentation implements OperationTransformer
{
    public const string SCHEME = 'orderToken';

    public static function scheme(): SecurityScheme
    {
        return SecurityScheme::apiKey('header', OrderAccessToken::HEADER)
            ->as(self::SCHEME)
            ->setDescription("A guest's order access token, given in the X-Order-Token header of the response that placed the order, and freshly again (replacing it) when that request is retried with the same Idempotency-Key. Only ever sent in this header: a token in the URL is never accepted.");
    }

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (str_starts_with((string) $routeInfo->route->getName(), 'guest.orders.')) {
            $operation->security = [new SecurityRequirement([self::SCHEME => []])];
        }
    }
}
