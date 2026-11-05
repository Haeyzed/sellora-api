<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when staff decide otherwise than a return's reason claims (that the store was or wasn't at fault) without saying why: fault_overruled_reason is required then.
 */
final class ReturnFaultReasonRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'return_fault_reason_required';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['fault_overruled_reason' => [$this->translatedMessage()]];
    }
}
