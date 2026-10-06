<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Exceptions;

use App\Landlord\Plans\Models\Plan;
use App\Shared\Exceptions\DomainException;

/**
 * Raised when a store would be put on a plan that has been retired.
 */
final class PlanNotAvailableException extends DomainException
{
    public function __construct(Plan $plan)
    {
        parent::__construct(['plan' => $plan->name]);
    }

    public function errorCode(): string
    {
        return 'plan_not_available';
    }
}
