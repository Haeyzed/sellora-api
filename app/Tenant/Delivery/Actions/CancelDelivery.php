<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Exceptions\DeliveryTransitionNotAllowedException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Services\DeliveryActivity;
use App\Tenant\Delivery\Services\DeliveryProgress;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Takes a delivery back from its driver before they pick the goods up: staff changing their mind, the shipment cancelled, or the driver deactivated.
 */
final readonly class CancelDelivery
{
    public function __construct(
        private DeliveryProgress $deliveryProgress,
        private DeliveryActivity $deliveryActivity,
    ) {}

    /**
     * Staff cancelling it, recorded in the activity log.
     *
     * @throws DeliveryTransitionNotAllowedException When it has been picked up, or isn't under way.
     */
    public function byStaff(StaffMember $actor, Delivery $delivery): Delivery
    {
        $cancelled = $this->handle($delivery, AccountReference::to($actor));
        $this->deliveryActivity->record($actor, $cancelled, 'delivery_cancelled', 'Cancelled');

        return $cancelled;
    }

    /**
     * @param  AccountReference|null  $by  Null for the system, such as when its shipment was cancelled.
     *
     * @throws DeliveryTransitionNotAllowedException
     */
    public function handle(Delivery $delivery, ?AccountReference $by): Delivery
    {
        return DB::transaction(fn (): Delivery => $this->deliveryProgress->move($this->deliveryProgress->lock($delivery), DeliveryStatus::Cancelled, $by));
    }

    /**
     * Cancels a delivery if it is still only assigned, leaving any other as it is. Says whether it did.
     */
    public function ifAssigned(Delivery $delivery, ?AccountReference $by): bool
    {
        return DB::transaction(function () use ($delivery, $by): bool {
            $locked = $this->deliveryProgress->lock($delivery);

            if ($locked->status !== DeliveryStatus::Assigned) {
                return false;
            }

            $this->deliveryProgress->move($locked, DeliveryStatus::Cancelled, $by);

            return true;
        });
    }
}
