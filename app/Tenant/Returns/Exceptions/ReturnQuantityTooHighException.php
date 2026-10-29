<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a return would send back more of a line than can still be returned of it: units that were never delivered (or, for staff, never shipped), or that are already in another return or refunded. Says how many of each such line are left.
 */
final class ReturnQuantityTooHighException extends DomainException
{
    /**
     * @param  array<string, int>  $left  What can still be returned of each line asked for too much of, by the line's public ID.
     */
    public function __construct(public readonly array $left)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'return_quantity_too_high';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        $errors = [];

        foreach ($this->left as $line => $left) {
            $message = __('errors.return_quantity_left', ['left' => $left]);
            $errors["lines.{$line}"] = [is_string($message) ? $message : $this->errorCode()];
        }

        return $errors === [] ? ['lines' => [$this->translatedMessage()]] : $errors;
    }
}
