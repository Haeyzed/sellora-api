<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Stores email addresses trimmed and lower-cased, so "Ada@Mail.com " and "ada@mail.com" are always the same account.
 */
trait HasNormalisedEmail
{
    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (string $email): string => mb_strtolower(trim($email)),
        );
    }
}
