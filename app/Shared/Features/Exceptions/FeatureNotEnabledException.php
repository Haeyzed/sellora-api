<?php

declare(strict_types=1);

namespace App\Shared\Features\Exceptions;

use App\Shared\Exceptions\DomainException;
use App\Shared\Features\FeatureState;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a store tries to use a module or integration its plan or status doesn't allow right now.
 *
 * The error code says why ("feature_locked", "feature_suspended"...), so the
 * dashboard can offer the right next step, such as upgrading the plan.
 */
final class FeatureNotEnabledException extends DomainException
{
    public function __construct(public readonly string $featureKey, public readonly FeatureState $state)
    {
        parent::__construct(['feature' => $featureKey]);
    }

    public function errorCode(): string
    {
        return 'feature_'.$this->state->value;
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
