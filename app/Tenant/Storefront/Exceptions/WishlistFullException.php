<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when saving would take a customer's wishlist past the most it may hold (config storefront.wishlist_max_items).
 */
final class WishlistFullException extends DomainException
{
    public function __construct(int $limit)
    {
        parent::__construct(['limit' => $limit]);
    }

    public function errorCode(): string
    {
        return 'wishlist_full';
    }
}
