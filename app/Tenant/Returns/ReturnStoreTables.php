<?php

declare(strict_types=1);

namespace App\Tenant\Returns;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How returns, their lines, their history, their refund attempts, where their refunds go, their exchanges and the return settings appear in a store export. Nothing in them is a secret.
 */
final class ReturnStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('returns', include: [
            'id', 'public_id', 'order_id', 'sequence', 'status', 'resolution', 'reason', 'stores_fault', 'fault_overruled_reason', 'note', 'is_test', 'keep_items', 'outside_window_reason',
            'undelivered_reason', 'rejection_reason', 'stock_location_id', 'requested_by_type', 'requested_by_id', 'requested_at', 'approved_at',
            'received_at', 'inspected_at', 'completed_at', 'rejected_at', 'cancelled_at', 'anonymised_at', 'created_at', 'updated_at',
        ]);

        $registry->table('return_lines', include: ['id', 'return_id', 'order_line_id', 'replacement_variant_id', 'quantity', 'restocked_quantity', 'written_off_quantity', 'created_at', 'updated_at']);

        $registry->table('return_status_changes', include: ['id', 'return_id', 'from_status', 'to_status', 'changed_by_type', 'changed_by_id', 'created_at']);

        $registry->table('return_refunds', include: [
            'id', 'return_id', 'attempt', 'idempotency_key', 'refund_id', 'status', 'refusal_code', 'shipping', 'refund_amount', 'withheld_amount', 'withheld_reason',
            'destination', 'requested_by_type', 'requested_by_id', 'created_at', 'updated_at',
        ]);

        $registry->table('return_refund_choices', include: ['id', 'return_id', 'destination', 'chosen_by_type', 'chosen_by_id', 'note', 'anonymised_at', 'created_at']);

        $registry->table('return_exchanges', include: [
            'id', 'public_id', 'return_id', 'order_id', 'status', 'stores_fault', 'shipping_charged', 'currency', 'returned_value_amount', 'credit_amount',
            'difference_amount', 'kept_amount', 'refund_id', 'confirmed_by_type', 'confirmed_by_id', 'committed_at', 'released_at', 'created_at', 'updated_at',
        ]);

        $registry->table('return_settings', include: ['id', 'window_days', 'customer_requests', 'photos_required', 'charge_shipping_on_exchanges', 'created_at', 'updated_at']);
    }
}
