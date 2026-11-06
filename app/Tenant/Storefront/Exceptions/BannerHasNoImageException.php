<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when an image is uploaded for an announcement, which shows text only.
 */
final class BannerHasNoImageException extends DomainException
{
    public function errorCode(): string
    {
        return 'banner_has_no_image';
    }

    public function fieldErrors(): array
    {
        return ['image' => [$this->translatedMessage()]];
    }
}
