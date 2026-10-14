<?php

declare(strict_types=1);

namespace App\Shared\Auth\Data;

/**
 * A fresh set of one-time recovery codes, in plain text only until they are shown.
 */
final readonly class RecoveryCodes
{
    /**
     * @param  list<string>  $codes
     */
    public function __construct(
        public array $codes,
    ) {}
}
