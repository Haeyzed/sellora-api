<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a return's refund is asked to go somewhere not on offer for it: a module's destination for a guest, for an order without an account, or while the module isn't Enabled, or a key no destination has.
 */
final class RefundDestinationNotOfferedException extends DomainException
{
    public function errorCode(): string
    {
        return 'refund_destination_not_offered';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['destination' => [$this->translatedMessage()]];
    }
}
