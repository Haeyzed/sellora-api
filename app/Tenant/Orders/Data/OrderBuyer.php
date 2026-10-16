<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Data;

use App\Tenant\Customers\Models\Customer;
use InvalidArgumentException;

/**
 * Who is buying: a signed-in customer, or a guest, with the contact details given at checkout, which the order keeps as a copy.
 */
final readonly class OrderBuyer
{
    /**
     * @param  string  $email  Kept in lower case.
     * @param  string|null  $phone  In E.164 format.
     *
     * @throws InvalidArgumentException When the email is empty.
     */
    public function __construct(
        public ?Customer $customer,
        public string $email,
        public ?string $name,
        public ?string $phone,
    ) {
        if (trim($email) === '') {
            throw new InvalidArgumentException('An order needs the buyer\'s email.');
        }
    }

    public static function guest(string $email, ?string $name = null, ?string $phone = null): self
    {
        return new self(null, mb_strtolower(trim($email)), $name, $phone);
    }

    /**
     * A signed-in customer, with the details they gave at checkout (their account's unless they changed them).
     */
    public static function customer(Customer $customer, ?string $email = null, ?string $name = null, ?string $phone = null): self
    {
        return new self($customer, mb_strtolower(trim($email ?? (string) $customer->email)), $name ?? $customer->name, $phone ?? $customer->phone);
    }
}
