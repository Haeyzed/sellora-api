<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Exceptions;

use App\Shared\Exceptions\DomainException;
use Throwable;

/**
 * Raised when a menu link goes to a page, category, brand or product the store doesn't have, or has in the trash.
 */
final class MenuLinkTargetNotFoundException extends DomainException
{
    /**
     * @param  string  $field  The link's target in the request, such as "items.0.children.1.target".
     */
    public function __construct(private readonly string $field, ?Throwable $previous = null)
    {
        parent::__construct(previous: $previous);
    }

    public function errorCode(): string
    {
        return 'menu_link_target_not_found';
    }

    public function fieldErrors(): array
    {
        return [$this->field => [$this->translatedMessage()]];
    }
}
