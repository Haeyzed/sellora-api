<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * Whether the store's account with a gateway is connected.
 */
enum ConnectionStatus: string
{
    case Connected = 'connected';
    /** Its credentials were removed. The row stays, since payments name it. */
    case Disconnected = 'disconnected';
}
