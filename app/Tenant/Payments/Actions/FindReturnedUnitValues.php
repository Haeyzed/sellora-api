<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Shared\Money\Money;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Exceptions\RefundInvalidException;
use App\Tenant\Payments\Services\RefundAmounts;

/**
 * What units of an order's lines would give back if refunded now, for Returns (Step 18d) to work out what an exchange's returned items are worth: what the buyer paid for them after discounts, tax included, exactly as a refund of them would (RefundAmounts, the one copy of that rule).
 *
 * Reading changes nothing.
 */
final readonly class FindReturnedUnitValues
{
    public function __construct(private RefundAmounts $refundAmounts) {}

    /**
     * @param  array<string, int>  $units  Units by order line public ID.
     * @return array<string, Money> What each line's units are worth, by order line public ID.
     *
     * @throws RefundInvalidException When a line isn't the order's, or fewer of its units are left to give back.
     */
    public function handle(Order $order, array $units): array
    {
        $values = [];

        foreach ($this->refundAmounts->lines($order, new RefundRequest(lines: $units)) as $line) {
            $values[$line->line->public_id] = $line->amount;
        }

        return $values;
    }
}
