<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How payments appear in a store export: every payment and its history, as the store's own financial records. Gateway credentials and webhook endpoint IDs never leave: only which gateways are connected and how.
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
        $registry->table('payments', include: [
            'id', 'public_id', 'order_id', 'method', 'gateway', 'connection_id', 'is_test', 'status', 'currency', 'amount_amount', 'received_currency',
            'received_amount', 'reference', 'idempotency_key', 'gateway_reference', 'redirect_url', 'failure_code', 'review_reason', 'card_brand',
            'card_last4', 'confirmed_by_type', 'confirmed_by_id', 'succeeded_at', 'failed_at', 'needs_review_at', 'unresolved_at', 'cancelled_at',
            'last_checked_at', 'checks', 'collection_alerted_at', 'created_at', 'updated_at',
        ]);
        $registry->table('payment_method_settings', include: ['id', 'method', 'enabled', 'deadline_hours', 'collection_alert_hours', 'instructions', 'created_at', 'updated_at']);
        $registry->table('payment_events', include: ['id', 'payment_id', 'type', 'data', 'actor_type', 'actor_id', 'created_at']);
        $registry->table('payment_webhook_events', include: ['id', 'connection_id', 'gateway', 'event_id', 'kind', 'reference', 'received_at', 'processed_at'], exclude: [
            'payload' => 'What a gateway sent, encrypted with the platform key and kept only briefly; what it led to is in the payments.',
        ]);
    }
}
