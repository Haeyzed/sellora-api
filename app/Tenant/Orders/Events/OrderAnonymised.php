<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Events;

use App\Tenant\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order's personal data was removed. Announced inside the PersonalDataErasure run that removed it, so other domains remove what they hold about its buyer in the same transaction (Payments: card brand and last four digits).
 */
final class OrderAnonymised
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
