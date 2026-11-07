<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Data;

use App\Tenant\Messaging\Enums\StaffAlertKind;

/**
 * One staff alert to raise: its kind, what it is about, and what its text shows. Never personal data.
 */
final readonly class StaffAlertData
{
    /**
     * @param  string  $subjectType  Such as "payment" or "product_variant".
     * @param  string  $subjectId  The public ID of what it is about, or a gateway's key.
     * @param  array<string, int|string|bool|null>  $details  Order numbers, amounts in minor units with their currency, SKUs.
     */
    public function __construct(
        public StaffAlertKind $kind,
        public string $subjectType,
        public string $subjectId,
        public array $details = [],
    ) {}
}
