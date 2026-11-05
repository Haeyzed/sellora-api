<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a return's replacements don't fit its resolution: an exchange names one variant (of the store's) for every returned line, a refund names none.
 */
final class ReturnReplacementInvalidException extends DomainException
{
    /**
     * @param  list<string>  $lines  The order lines' public IDs whose replacement is wrong.
     */
    public function __construct(public readonly array $lines)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'return_replacement_invalid';
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
