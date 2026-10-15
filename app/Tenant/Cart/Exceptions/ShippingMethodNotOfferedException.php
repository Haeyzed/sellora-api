<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when choosing a shipping method the cart isn't offered: not for where the shopper is, not for what is in the cart, or not offered at all. GET /cart/shipping-options lists the ones that are.
 */
final class ShippingMethodNotOfferedException extends DomainException
{
    public function errorCode(): string
    {
        return 'shipping_method_not_offered';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['shipping_method' => [$this->translatedMessage()]];
    }
}
