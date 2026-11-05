<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a variant an exchange would send can't be now: it is no longer for sale, or out of stock without backorders. Choose another, or settle the return with a refund. Shoppers are never told how many are left.
 */
final class ReturnReplacementNotAvailableException extends DomainException
{
    /**
     * @param  list<string>  $lines  The order lines' public IDs whose replacement can't be sent.
     */
    public function __construct(public readonly array $lines)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'return_replacement_not_available';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        $errors = [];

        foreach ($this->lines as $line) {
            $errors["replacements.{$line}"] = [$this->translatedMessage()];
        }

        return $errors;
    }
}
