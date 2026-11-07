<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Data;

use App\Landlord\Identity\Enums\PlatformAlertKind;

/**
 * A platform alert to send: its kind, the store it is about (if any), and what its text shows. Never personal data.
 */
final readonly class PlatformAlertData
{
    /**
     * @param  array<string, string|int>  $details  The text's placeholders: store, store_id, count, hours, stale_hours, as the kind has them.
     */
    public function __construct(
        public PlatformAlertKind $kind,
        public ?string $storeId,
        public array $details,
    ) {}
}
