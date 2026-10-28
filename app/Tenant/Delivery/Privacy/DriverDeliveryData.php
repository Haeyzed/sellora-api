<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Privacy;

use App\Shared\Auth\AccountReference;
use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\DeliveryStatusChange;

/**
 * The deliveries the driver was given and the status changes they made, without anything about the buyers (no recipient, address or failure note). Business records: erasing the driver keeps them, pointing at the erased driver.
 */
final readonly class DriverDeliveryData implements PersonalDataHandler
{
    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        $driver = DriverDataSubject::driver($subject);

        if ($driver === null) {
            return [];
        }

        $by = AccountReference::to($driver);
        $changes = DeliveryStatusChange::query()
            ->where('changed_by_type', $by->type)
            ->where('changed_by_id', $by->publicId)
            ->orderBy('id')
            ->get()
            ->groupBy('delivery_id');

        return array_values(Delivery::query()->with('shipment')->where('driver_id', $driver->id)->orderBy('id')->get()->map(static fn (Delivery $delivery): array => [
            'id' => $delivery->public_id,
            'shipment' => $delivery->shipment->public_id,
            'attempt' => $delivery->attempt,
            'status' => $delivery->status->value,
            'failure_reason' => $delivery->failure_reason?->value,
            'assigned_at' => $delivery->assigned_at->toIso8601String(),
            'picked_up_at' => $delivery->picked_up_at?->toIso8601String(),
            'on_the_way_at' => $delivery->on_the_way_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'failed_at' => $delivery->failed_at?->toIso8601String(),
            'status_changes_by_driver' => array_values(($changes->get($delivery->id) ?? collect())->map(static fn (DeliveryStatusChange $change): array => [
                'from' => $change->from_status?->value,
                'to' => $change->to_status->value,
                'at' => $change->created_at->toIso8601String(),
            ])->all()),
        ])->all());
    }

    /**
     * Keeps the deliveries and their history: the store's record of who held which goods.
     */
    public function erase(DataSubject $subject): void {}
}
