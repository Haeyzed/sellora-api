<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Privacy;

use App\Shared\Auth\AccountReference;
use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Orders\Models\Order;

/**
 * A customer's orders, guest orders that joined their account included: exported as they stand, and anonymised on erasure once settled (OrderPrivacy).
 */
final readonly class CustomerOrderData implements PersonalDataHandler
{
    public function __construct(private OrderPrivacy $orderPrivacy) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);

        return $customer === null ? [] : $this->orderPrivacy->export(Order::query()->where('customer_id', $customer->id));
    }

    public function erase(DataSubject $subject): void
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer !== null) {
            $this->orderPrivacy->erase(Order::query()->where('customer_id', $customer->id), AccountReference::to($customer));
        }
    }
}
