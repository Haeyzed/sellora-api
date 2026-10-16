<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Promotions\Contracts\OrderHistory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Whether a buyer has ordered before, for first-order promotions (the Step 11 contract): any order that wasn't cancelled, by their account or by the email it was placed with, guest orders included.
 *
 * Only the email an order was placed with counts: a contact correction staff
 * make later never changes whose first order it was.
 */
final readonly class PlacedOrderHistory implements OrderHistory
{
    public function hasOrdered(?Customer $customer, ?string $email): bool
    {
        $email = $email === null ? null : mb_strtolower(trim($email));

        if ($customer === null && ($email === null || $email === '')) {
            return false;
        }

        return Order::query()
            ->where('status', '<>', OrderStatus::Cancelled->value)
            ->where(static function (Builder $buyer) use ($customer, $email): void {
                if ($customer !== null) {
                    $buyer->orWhere('customer_id', $customer->id);
                }

                if ($email !== null && $email !== '') {
                    $buyer->orWhere('contact_email', $email);
                }
            })
            ->exists();
    }
}
