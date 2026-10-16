<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Data;

/**
 * An order's contact details as they stand: the latest correction, or as placed.
 */
final readonly class OrderContact
{
    /**
     * @param  string|null  $email  In lower case; null once anonymised.
     * @param  string|null  $phone  In E.164 format.
     * @param  bool  $isCorrected  True when staff corrected them after placement.
     */
    public function __construct(
        public ?string $email,
        public ?string $name,
        public ?string $phone,
        public bool $isCorrected,
    ) {}
}
