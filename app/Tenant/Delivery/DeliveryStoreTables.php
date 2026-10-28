<?php

declare(strict_types=1);

namespace App\Tenant\Delivery;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How drivers, their deliveries, where they went and the cash they hold appear in a store export. Their PINs and setup codes never leave the store.
 */
final class DeliveryStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table(
            'drivers',
            include: ['id', 'public_id', 'name', 'phone', 'is_active', 'last_signed_in_at', 'erased_at', 'created_at', 'updated_at'],
            exclude: ['pin' => 'PIN hash, a secret.'],
        );

        $registry->table(
            'driver_setup_codes',
            include: ['id', 'driver_id', 'failed_attempts', 'expires_at', 'used_at', 'voided_at', 'issued_by_type', 'issued_by_id', 'created_at', 'updated_at'],
            exclude: ['code_hash' => 'Hash of a one-time PIN setup code, a secret.'],
        );

        $registry->table('deliveries', include: [
            'id', 'public_id', 'shipment_id', 'driver_id', 'attempt', 'status', 'failure_reason', 'failure_note', 'recipient_name',
            'assigned_at', 'picked_up_at', 'on_the_way_at', 'delivered_at', 'failed_at', 'cancelled_at', 'resolved_at', 'resolution',
            'assigned_by_type', 'assigned_by_id', 'created_at', 'updated_at',
        ]);

        $registry->table('driver_cash_entries', include: [
            'id', 'public_id', 'driver_id', 'type', 'cash_amount', 'cash_currency', 'delivery_id', 'payment_public_id', 'recorded_by_type', 'recorded_by_id', 'created_at',
        ]);

        $registry->table('driver_locations', include: ['id', 'delivery_id', 'driver_id', 'latitude', 'longitude', 'accuracy_meters', 'created_at']);

        $registry->table('delivery_status_changes', include: ['id', 'delivery_id', 'from_status', 'to_status', 'changed_by_type', 'changed_by_id', 'created_at']);
    }
}
