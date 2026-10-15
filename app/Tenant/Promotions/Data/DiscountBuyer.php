<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Data;

use App\Tenant\Customers\Models\Customer;

/**
 * Who is buying, as far as discounts care: the signed-in customer and their groups, or a guest known only by email.
 */
final readonly class DiscountBuyer
{
    /**
     * @param  string|null  $email  Lower case: the customer's email, or the guest's; null for a guest who hasn't given one yet.
     * @param  list<int>  $groupIds  The customer's groups; none for a guest.
     */
    private function __construct(
        public ?Customer $customer,
        public ?string $email,
        public array $groupIds,
    ) {}

    public static function customer(Customer $customer): self
    {
        return new self($customer, $customer->email, array_values(array_map(intval(...), $customer->groups()->pluck('customer_groups.id')->all())));
    }

    public static function guest(?string $email): self
    {
        $email = $email === null ? null : mb_strtolower(trim($email));

        return new self(null, $email === '' ? null : $email, []);
    }
}
