<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the base currency or tax mode would change while the store has something priced, which would silently change what customers pay.
 */
final class PricingSettingsLockedException extends DomainException
{
    public function errorCode(): string
    {
        return 'pricing_settings_locked';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
