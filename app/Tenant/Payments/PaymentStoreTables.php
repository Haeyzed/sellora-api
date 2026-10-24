<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How payments appear in a store export: every payment, refund and their history, as the store's own financial records. Gateway credentials and webhook endpoint IDs never leave: only which gateways are connected and how.
 */
final class PaymentStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('payment_gateway_connections', include: [
            'id', 'public_id', 'gateway', 'status', 'enabled', 'priority', 'mode', 'credentials_hint', 'account_id',
            'connected_at', 'checked_at', 'needs_attention_at', 'disconnected_at', 'created_at', 'updated_at',
        ], exclude: [
            'credentials' => 'The gateway keys of the store: secrets, never exported.',
            'retired_credentials' => 'Earlier gateway keys of the store, kept while money they took settles: secrets, never exported.',
            'test_endpoint_id' => 'Its test-mode webhook endpoint ID: lets anyone holding it send webhooks to the store.',
            'live_endpoint_id' => 'Its live-mode webhook endpoint ID: lets anyone holding it send webhooks to the store.',
        ]);
        $registry->table('payments', include: [
            'id', 'public_id', 'order_id', 'method', 'gateway', 'connection_id', 'is_test', 'status', 'currency', 'amount_amount', 'received_currency',
            'received_amount', 'reference', 'idempotency_key', 'gateway_reference', 'redirect_url', 'failure_code', 'review_reason', 'card_brand',
            'card_last4', 'confirmed_by_type', 'confirmed_by_id', 'succeeded_at', 'failed_at', 'needs_review_at', 'unresolved_at', 'cancelled_at',
            'last_checked_at', 'checks', 'collection_alerted_at', 'created_at', 'updated_at',
        ]);
        $registry->table('payment_method_settings', include: ['id', 'method', 'enabled', 'deadline_hours', 'collection_alert_hours', 'instructions', 'created_at', 'updated_at']);
        $registry->table('payment_events', include: ['id', 'payment_id', 'type', 'data', 'actor_type', 'actor_id', 'created_at']);
        $registry->table('refunds', include: [
            'id', 'public_id', 'order_id', 'currency', 'amount_amount', 'shipping_amount', 'shipping_tax_amount', 'purpose', 'reason', 'idempotency_key',
            'requested_by_type', 'requested_by_id', 'created_at',
        ]);
        $registry->table('refund_lines', include: ['id', 'refund_id', 'order_line_id', 'quantity', 'currency', 'amount_amount', 'tax_amount', 'created_at']);
        $registry->table('payment_refunds', include: [
            'id', 'public_id', 'refund_id', 'payment_id', 'status', 'currency', 'amount_amount', 'reference', 'gateway_reference', 'failure_code',
            'recorded_by_type', 'recorded_by_id', 'succeeded_at', 'failed_at', 'last_checked_at', 'checks', 'slow_alerted_at',
            'send_attempts', 'not_found_at', 'needs_review_at', 'review_reason', 'created_at', 'updated_at',
        ]);
        $registry->table('payment_webhook_events', include: ['id', 'connection_id', 'gateway', 'event_id', 'kind', 'reference', 'received_at', 'processed_at'], exclude: [
            'payload' => 'What a gateway sent, encrypted with the platform key and kept only briefly; what it led to is in the payments.',
        ]);
    }
}
