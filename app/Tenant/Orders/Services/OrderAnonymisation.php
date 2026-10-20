<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Orders\Events\OrderAnonymised;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderNote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Anonymises orders, which are financial records and so are never deleted (section 3.3): the buyer's contact details, note, addresses (all but the country and state) and corrections go, and staff notes, which may name them; the amounts, tax and country stay.
 *
 * Only inside a PersonalDataErasure run: the database refuses it otherwise.
 * Other domains remove theirs through OrderAnonymised, in the same run.
 */
final readonly class OrderAnonymisation
{
    public function __construct(private OrderEventLog $orderEventLog) {}

    /**
     * Whether an order is settled, so it can be anonymised: completed or cancelled, owing no refund, with no shipment still on its way.
     */
    public function isSettled(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true)
            && ! $order->refund_due
            && ! $order->shipments()->whereIn('status', [ShipmentStatus::Pending->value, ShipmentStatus::ReadyForPickup->value, ShipmentStatus::Shipped->value])->exists();
    }

    /**
     * Anonymises one order, inside a PersonalDataErasure run.
     */
    public function anonymise(Order $order): void
    {
        DB::table('orders')->where('id', $order->id)->update([
            'contact_email' => null,
            'contact_name' => null,
            'contact_phone' => null,
            'customer_note' => null,
            'anonymise_when_settled' => false,
            'anonymised_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
        DB::table('order_addresses')->where('order_id', $order->id)->update([
            'recipient_name' => null, 'company' => null, 'line1' => null, 'line2' => null, 'city' => null, 'postal_code' => null, 'phone' => null,
        ]);
        DB::table('order_contact_corrections')->where('order_id', $order->id)->update(['email' => null, 'name' => null, 'phone' => null]);
        OrderNote::query()->where('order_id', $order->id)->delete();

        $this->orderEventLog->record($order, OrderEventType::Anonymised);
        OrderAnonymised::dispatch($order);
    }
}
