<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Data;

use App\Shared\Auth\AccountReference;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Who is asking for a return: the signed-in customer whose order it is, a guest holding the order's access token, or staff for the buyer.
 *
 * Buyers are held to the store's settings, the window and delivered units;
 * staff may go further, saying why.
 */
final readonly class ReturnRequester
{
    private function __construct(
        public ?AccountReference $account,
        public bool $isStaff,
    ) {}

    public static function customer(Customer $customer): self
    {
        return new self(AccountReference::to($customer), false);
    }

    public static function guest(): self
    {
        return new self(null, false);
    }

    public static function staff(StaffMember $staffMember): self
    {
        return new self(AccountReference::to($staffMember), true);
    }
}
