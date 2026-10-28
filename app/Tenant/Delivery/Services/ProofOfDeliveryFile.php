<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

use App\Tenant\Delivery\Enums\ProofOfDeliveryKind;

/**
 * One proof of delivery image, re-encoded into a temporary file without its metadata, ready to be kept with the delivery.
 */
final readonly class ProofOfDeliveryFile
{
    public function __construct(
        public ProofOfDeliveryKind $kind,
        public string $path,
        public string $extension,
        public int $size,
    ) {}

    /**
     * The name it is kept under: its kind, never the name it was uploaded with.
     */
    public function fileName(): string
    {
        return "{$this->kind->value}.{$this->extension}";
    }
}
