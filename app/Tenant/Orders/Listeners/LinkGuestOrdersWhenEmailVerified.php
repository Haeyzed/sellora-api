<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Listeners;

use App\Tenant\Customers\Events\CustomerEmailVerified;
use App\Tenant\Orders\Actions\LinkGuestOrders;

/**
 * Links the guest orders placed with a customer's email once they prove it is theirs.
 */
final readonly class LinkGuestOrdersWhenEmailVerified
{
    public function __construct(private LinkGuestOrders $linkGuestOrders) {}

    public function handle(CustomerEmailVerified $event): void
    {
        $this->linkGuestOrders->handle($event->customer);
    }
}
