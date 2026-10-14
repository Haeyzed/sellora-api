<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Models\CustomerGroup;

/**
 * The customer groups staff put the customer in. Erasure takes the customer out of every group; the groups stay.
 */
final readonly class CustomerGroupData implements PersonalDataHandler
{
    /**
     * @return list<array{id: string, name: string}>
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return [];
        }

        return array_values($customer->groups()->orderBy('customer_groups.id')->get()->map(static fn (CustomerGroup $group): array => [
            'id' => $group->public_id,
            'name' => $group->name,
        ])->all());
    }

    public function erase(DataSubject $subject): void
    {
        CustomerDataSubject::customer($subject)?->groups()->detach();
    }
}
