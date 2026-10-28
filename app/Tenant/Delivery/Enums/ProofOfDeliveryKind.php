<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Enums;

/**
 * The kinds of proof a delivered delivery keeps: a photo of the handed-over goods, and the recipient's signature. At least one is required.
 */
enum ProofOfDeliveryKind: string
{
    case Photo = 'photo';
    case Signature = 'signature';

    /**
     * The media collection the file is kept in, on the delivery.
     */
    public function collection(): string
    {
        return match ($this) {
            self::Photo => 'delivery_photo',
            self::Signature => 'recipient_signature',
        };
    }
}
