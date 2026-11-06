<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Data;

/**
 * A menu's key and name as staff send them; null leaves a field as it is.
 */
final readonly class MenuData
{
    public function __construct(
        public ?string $key = null,
        public ?string $name = null,
    ) {}
}
