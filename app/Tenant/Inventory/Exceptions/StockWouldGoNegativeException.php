<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a change would leave less than nothing on hand, such as removing 5 when 3 are there.
 */
final class StockWouldGoNegativeException extends DomainException
{
    /**
     * @param  string  $field  The input field the change was sent in.
     */
    public function __construct(private readonly string $field, int $onHand)
    {
        parent::__construct(['on_hand' => $onHand]);
    }

    public function errorCode(): string
    {
        return 'stock_would_go_negative';
    }

    public function fieldErrors(): array
    {
        return [$this->field => [$this->translatedMessage()]];
    }
}
