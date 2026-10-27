<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Orders\Exceptions\ShipmentClosedException;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Orders\Services\OrderActivity;
use Illuminate\Support\Facades\DB;

/**
 * Staff setting a shipment's tracking number and link, until it arrives or is cancelled. Audited.
 */
final readonly class UpdateShipmentTracking
{
    public function __construct(private OrderActivity $orderActivity) {}

    /**
     * @param  string|null  $trackingUrl  Always https.
     *
     * @throws ShipmentClosedException When it has arrived, was cancelled, or came back.
     */
    public function handle(StaffMember $actor, Shipment $shipment, ?string $trackingNumber, ?string $trackingUrl): Shipment
    {
        return DB::transaction(function () use ($actor, $shipment, $trackingNumber, $trackingUrl): Shipment {
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [ShipmentStatus::Delivered, ShipmentStatus::Cancelled, ShipmentStatus::Returned], true)) {
                throw new ShipmentClosedException;
            }

            $locked->forceFill(['tracking_number' => $trackingNumber, 'tracking_url' => $trackingUrl])->save();
            $this->orderActivity->record($actor, $locked->order, 'shipment_tracking_changed', 'Changed the tracking of a shipment of');

            return $locked->load('lines.orderLine');
        });
    }
}
