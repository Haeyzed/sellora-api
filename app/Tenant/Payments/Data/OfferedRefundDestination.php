<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

/**
 * Somewhere a customer may choose to have a refund go, as they see it: its key and its name in their language.
 */
final readonly class OfferedRefundDestination
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}
