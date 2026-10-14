<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Models\CustomerAddress;

/**
 * A customer's saved addresses. Orders keep their own copies; erasure deletes the saved ones.
 */
final readonly class CustomerAddressData implements PersonalDataHandler
{
    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return [];
        }

        return array_values($customer->addresses()->orderBy('id')->get()->map(static fn (CustomerAddress $address): array => [
            'id' => $address->public_id,
            'label' => $address->label,
            ...$address->snapshot()->toArray(),
            'created_at' => $address->created_at?->toIso8601String(),
        ])->all());
    }

    public function erase(DataSubject $subject): void
    {
        CustomerDataSubject::customer($subject)?->addresses()->delete();
    }
}
