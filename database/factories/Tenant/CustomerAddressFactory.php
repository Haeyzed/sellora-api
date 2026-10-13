<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A Lagos address by default.
 *
 * @extends Factory<CustomerAddress>
 */
final class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'recipient_name' => fake()->name(),
            'line1' => '12 Admiralty Way',
            'city' => 'Lekki',
            'state_code' => 'LA',
            'postal_code' => '106104',
            'country_code' => 'NG',
        ];
    }
}
