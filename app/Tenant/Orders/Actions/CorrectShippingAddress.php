<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Enums\FulfilmentStatus;
use App\Tenant\Orders\Enums\OrderAddressKind;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Exceptions\OrderAddressCannotBeCorrectedException;
use App\Tenant\Orders\Exceptions\OrderAddressRegionChangedException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAddress;
use App\Tenant\Orders\Services\OrderActivity;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Orders\Services\OrderProgress;
use Illuminate\Support\Facades\DB;

/**
 * Staff correcting where an open order ships to, before anything is sent (section 13): a new version of the address, kept beside the one placed, which never changes.
 *
 * Only within the same country and state, so the order's tax and shipping
 * price still hold; anything further means cancelling and placing it again.
 */
final readonly class CorrectShippingAddress
{
    public function __construct(
        private OrderProgress $orderProgress,
        private OrderEventLog $orderEventLog,
        private OrderActivity $orderActivity,
    ) {}

    /**
     * @throws OrderAddressCannotBeCorrectedException When the order isn't open, ships to no address, or has a shipment.
     * @throws OrderAddressRegionChangedException When the correction is in another country or state.
     */
    public function handle(StaffMember $actor, Order $order, AddressSnapshot $address): Order
    {
        return DB::transaction(function () use ($actor, $order, $address): Order {
            $locked = $this->orderProgress->lock($order);
            $current = $locked->load('addresses')->currentAddress(OrderAddressKind::Shipping);

            if ($locked->status !== OrderStatus::Open || $current === null || $locked->fulfilment_status !== FulfilmentStatus::Unfulfilled || $this->orderProgress->hasShipments($locked)) {
                throw new OrderAddressCannotBeCorrectedException;
            }

            if ($address->countryCode !== $current->country_code || mb_strtoupper((string) $address->stateCode) !== mb_strtoupper((string) $current->state_code)) {
                throw new OrderAddressRegionChangedException;
            }

            (new OrderAddress)->forceFill([
                'order_id' => $locked->id,
                'kind' => OrderAddressKind::Shipping,
                'version' => $current->version + 1,
                ...$address->toArray(),
                'created_by_type' => $actor->getMorphClass(),
                'created_by_id' => $actor->public_id,
            ])->save();

            $this->orderEventLog->record($locked, OrderEventType::AddressCorrected, ['kind' => OrderAddressKind::Shipping->value, 'version' => $current->version + 1], AccountReference::to($actor));
            $this->orderActivity->record($actor, $locked, 'order_address_corrected', 'Corrected the shipping address of');

            return $locked->unsetRelation('addresses');
        });
    }
}
