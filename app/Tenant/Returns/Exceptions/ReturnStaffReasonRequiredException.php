<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when staff start a return that goes past what a buyer could ask for without saying why: units delivered longer ago than the window allows (outside_window_reason), or shipped but never marked delivered (undelivered_reason).
 */
final class ReturnStaffReasonRequiredException extends DomainException
{
    /**
     * @param  list<'outside_window_reason'|'undelivered_reason'>  $fields  The reasons it needs.
     */
    public function __construct(public readonly array $fields)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'return_staff_reason_required';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        $errors = [];

        foreach ($this->fields as $field) {
            $errors[$field] = [$this->translatedMessage()];
        }

        return $errors;
    }
}
