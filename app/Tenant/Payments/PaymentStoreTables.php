<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How payments appear in a store export. Gateway credentials and webhook endpoint IDs never leave: only which gateways are connected and how.
 */
final class PaymentStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('payment_gateway_connections', include: [
            'id', 'public_id', 'gateway', 'status', 'enabled', 'priority', 'mode', 'credentials_hint', 'account_id',
            'connected_at', 'checked_at', 'disconnected_at', 'created_at', 'updated_at',
        ], exclude: [
            'credentials' => 'The gateway keys of the store: secrets, never exported.',
            'endpoint_id' => 'Its webhook endpoint ID: lets anyone holding it send webhooks to the store.',
        ]);
    }
}
