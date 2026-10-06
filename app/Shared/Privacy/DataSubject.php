<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

/**
 * The person a data-protection request is about, such as one customer or one driver.
 *
 * The type names the kind of person ("customer", "staff_member", "driver"),
 * chosen by the domain that owns them. The public ID identifies the person
 * within the current store.
 */
final readonly class DataSubject
{
    public function __construct(
        public string $type,
        public string $publicId,
    ) {}
}
