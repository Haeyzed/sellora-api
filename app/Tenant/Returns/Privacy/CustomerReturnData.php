<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Orders\Models\Order;

/**
 * A customer's returns, of every order of theirs: exported as they stand. On erasure their note and photos go with their order's anonymisation, so nothing is done here.
 */
final readonly class CustomerReturnData implements PersonalDataHandler
{
    public function __construct(private ReturnPrivacy $returnPrivacy) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);

        return $customer === null ? [] : $this->returnPrivacy->export(Order::query()->where('customer_id', $customer->id));
    }

    public function erase(DataSubject $subject): void {}
}
