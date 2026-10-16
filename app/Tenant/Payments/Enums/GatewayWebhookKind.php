<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * What a gateway webhook is about.
 */
enum GatewayWebhookKind: string
{
    /** A payment changed: ask the gateway where it stands. */
    case Payment = 'payment';
    /** A refund changed: ask the gateway where it stands. */
    case Refund = 'refund';
    /** A buyer disputed a payment with their bank: recorded and staff are told; no money state changes (disputes come later). */
    case Dispute = 'dispute';
    /** Something we don't act on. */
    case Ignored = 'ignored';
}
