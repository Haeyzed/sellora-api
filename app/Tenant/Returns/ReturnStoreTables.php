<?php

declare(strict_types=1);

namespace App\Tenant\Returns;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How returns, their lines, their history and the return settings appear in a store export. Nothing in them is a secret.
 */
final class ReturnStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('returns', include: [
            'id', 'public_id', 'order_id', 'sequence', 'status', 'resolution', 'reason', 'note', 'is_test', 'keep_items', 'outside_window_reason',
            'undelivered_reason', 'rejection_reason', 'stock_location_id', 'requested_by_type', 'requested_by_id', 'requested_at', 'approved_at',
            'received_at', 'inspected_at', 'completed_at', 'rejected_at', 'cancelled_at', 'anonymised_at', 'created_at', 'updated_at',
        ]);

        $registry->table('return_lines', include: ['id', 'return_id', 'order_line_id', 'quantity', 'restocked_quantity', 'written_off_quantity', 'created_at', 'updated_at']);

        $registry->table('return_status_changes', include: ['id', 'return_id', 'from_status', 'to_status', 'changed_by_type', 'changed_by_id', 'created_at']);

        $registry->table('return_settings', include: ['id', 'window_days', 'customer_requests', 'photos_required', 'created_at', 'updated_at']);
    }
}
