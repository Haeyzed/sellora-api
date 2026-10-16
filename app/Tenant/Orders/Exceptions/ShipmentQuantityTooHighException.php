<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a shipment would send more of a line than is left of it, or a line that doesn't ship. Says how many of each such line are left.
 */
final class ShipmentQuantityTooHighException extends DomainException
{
    /**
     * @param  array<string, int>  $left  What is left to ship of each line asked for too much of, by the line's public ID; 0 for a line that doesn't ship.
     */
    public function __construct(public readonly array $left)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'shipment_quantity_too_high';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        $errors = [];

        foreach ($this->left as $line => $left) {
            $message = __('errors.shipment_quantity_left', ['left' => $left]);
            $errors["lines.{$line}"] = [is_string($message) ? $message : $this->errorCode()];
        }

        return $errors === [] ? ['lines' => [$this->translatedMessage()]] : $errors;
    }
}
