<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a correction moves an order's shipping address to another country or state, where its tax and shipping price would no longer hold. Cancel and place it again instead.
 */
final class OrderAddressRegionChangedException extends DomainException
{
    public function errorCode(): string
    {
        return 'order_address_region_changed';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        $message = $this->translatedMessage();

        return ['country' => [$message], 'state' => [$message]];
    }
}
