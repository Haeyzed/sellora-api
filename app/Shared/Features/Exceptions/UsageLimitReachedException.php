<?php

declare(strict_types=1);

namespace App\Shared\Features\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a store tries to create more of something than its plan allows, such as a 251st product on a 250-product plan.
 *
 * Nothing is ever deleted when a store is over a limit (for example after a
 * downgrade); it only can't create more until it upgrades or removes some.
 */
final class UsageLimitReachedException extends DomainException
{
    public function __construct(public readonly string $limitKey, public readonly int $limit)
    {
        parent::__construct(['limit' => $limit]);
    }

    public function errorCode(): string
    {
        return 'usage_limit_reached';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
