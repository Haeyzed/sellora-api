<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when an inspection doesn't say what became of every unit that came back: a line of the return left out, a line that isn't the return's, or restocked and written off not adding up to a line's quantity.
 */
final class ReturnInspectionMismatchException extends DomainException
{
    /**
     * @param  list<string>  $lines  The order lines (public IDs) that don't add up, are missing or aren't the return's; empty when the whole list is wrong.
     */
    public function __construct(public readonly array $lines)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'return_inspection_mismatch';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        $errors = [];

        foreach ($this->lines as $line) {
            $errors["lines.{$line}"] = [$this->translatedMessage()];
        }

        return $errors === [] ? ['lines' => [$this->translatedMessage()]] : $errors;
    }
}
