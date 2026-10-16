<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Services\OrderEventLog;
use Illuminate\Support\Facades\DB;

/**
 * Links guest orders to the account whose verified email they were placed with (section 14), so the customer sees them in their history.
 *
 * Only on a verified email, never an unverified one, or anyone could claim
 * another person's orders by signing up with their email. Only the email an
 * order was placed with counts, never a correction. An order already in an
 * account stays there.
 */
final readonly class LinkGuestOrders
{
    public function __construct(private OrderEventLog $orderEventLog) {}

    /**
     * @return int How many orders joined the account.
     */
    public function handle(Customer $customer): int
    {
        if ($customer->email_verified_at === null || $customer->email === null) {
            return 0;
        }

        $email = mb_strtolower($customer->email);

        return DB::transaction(function () use ($customer, $email): int {
            $orders = Order::query()->whereNull('customer_id')->where('contact_email', $email)->orderBy('id')->lockForUpdate()->get();

            foreach ($orders as $order) {
                $order->forceFill(['customer_id' => $customer->id])->save();
                $this->orderEventLog->record($order, OrderEventType::LinkedToCustomer, ['customer' => $customer->public_id, 'when' => 'email_verified']);
            }

            return $orders->count();
        });
    }
}
