<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a new store owner doesn't accept the terms of service version in force, for example because a new version took effect while the page was open.
 */
final class TermsOfServiceNotAcceptedException extends DomainException
{
    public function errorCode(): string
    {
        return 'terms_of_service_not_accepted';
    }

    public function fieldErrors(): array
    {
        return ['accepted_terms_of_service' => [$this->translatedMessage()]];
    }
}
