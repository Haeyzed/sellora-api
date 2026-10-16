<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Events;

use App\Tenant\Customers\Models\Customer;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A customer proved they receive mail at their email: verified it, or confirmed a change to it. Orders links guest orders placed with it to the account. Announced only once it is saved.
 */
final class CustomerEmailVerified implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Customer $customer) {}
}
