<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Shared\Money\Money;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Payments\Data\RefundedLine;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Exceptions\RefundInvalidException;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Payments\Models\RefundLine;
use Illuminate\Database\Eloquent\Builder;

/**
 * What lines and shipping are worth when given back: what the buyer actually paid for them, after discounts, tax included. Never re-runs promotions.
 *
 * A line's total and its tax are each split across its units by largest
 * remainder (leftover minor units on the first units), so refunding a line
 * a few units at a time always adds up to exactly what it cost. Units and
 * shipping are counted as given back while any part of their refund holds
 * money; a refund that failed entirely frees them again.
 */
final readonly class RefundAmounts
{
    /**
     * The lines asked for, each with its value; throws when one isn't the order's or has fewer units left.
     *
     * @return list<RefundedLine>
     *
     * @throws RefundInvalidException
     */
    public function lines(Order $order, RefundRequest $request): array
    {
        if ($request->lines === []) {
            return [];
        }

        $lines = OrderLine::query()->where('order_id', $order->id)->whereIn('public_id', array_keys($request->lines))->get()->keyBy('public_id');
        $given = $this->unitsGivenBack($lines->map(static fn (OrderLine $line): int => $line->id)->values()->all());
        $refunded = [];

        foreach ($request->lines as $publicId => $quantity) {
            $line = $lines->get($publicId) ?? throw new RefundInvalidException;
            $before = $given[$line->id] ?? 0;

            if ($quantity < 1 || $before + $quantity > $line->quantity) {
                throw new RefundInvalidException;
            }

            $refunded[] = new RefundedLine($line, $quantity, $this->units($line->total, $line->quantity, $before, $quantity), $this->units($line->tax, $line->quantity, $before, $quantity));
        }

        return $refunded;
    }

    /**
     * The lines with an amount kept back from them (Returns, Step 18): spread across them by what each is worth, by largest remainder, each line's tax going down in the same proportion. Must be less than the lines are worth.
     *
     * @param  list<RefundedLine>  $lines
     * @return list<RefundedLine>
     *
     * @throws RefundInvalidException When there are no lines, or it isn't positive, or is at least what the lines are worth.
     */
    public function withhold(array $lines, Money $withheld): array
    {
        if ($lines === []) {
            throw new RefundInvalidException;
        }

        $worth = Money::sum($withheld->currency(), array_map(static fn (RefundedLine $line): Money => $line->amount, $lines));

        if (! $withheld->isPositive() || ! $withheld->hasSameCurrencyAs($worth) || ! $withheld->isLessThan($worth)) {
            throw new RefundInvalidException;
        }

        $parts = $withheld->allocate(array_map(static fn (RefundedLine $line): int => $line->amount->minorAmount(), $lines));
        $kept = [];

        foreach ($lines as $index => $line) {
            $part = $parts[$index];
            // The tax kept back is the line's share of tax in what is kept back from it.
            $taxPart = $line->amount->isZero() ? Money::zero($line->tax->currency()) : $part->allocate([$line->tax->minorAmount(), $line->amount->minorAmount() - $line->tax->minorAmount()])[0];
            $kept[] = new RefundedLine($line->line, $line->quantity, $line->amount->subtract($part), $line->tax->subtract($taxPart));
        }

        return $kept;
    }

    /**
     * What was charged for shipping and its tax, when asked for; throws when nothing was charged or it was already given back.
     *
     * @return array{Money, Money} The shipping, tax included, and its tax; zero when not asked for.
     *
     * @throws RefundInvalidException
     */
    public function shipping(Order $order, RefundRequest $request): array
    {
        if (! $request->shipping) {
            return [Money::zero($order->currency), Money::zero($order->currency)];
        }

        $shipping = $order->shippingLine;
        $alreadyGiven = Refund::query()
            ->where('order_id', $order->id)
            ->where('shipping_amount', '>', 0)
            ->whereHas('paymentRefunds', static fn (Builder $parts) => $parts->whereIn('status', RefundStatus::holdingMoney()))
            ->exists();

        if ($shipping === null || ! $shipping->total->isPositive() || $alreadyGiven) {
            throw new RefundInvalidException;
        }

        return [$shipping->total, $shipping->tax];
    }

    /**
     * Units already given back of each line, in refunds still holding money.
     *
     * @param  array<int, int>  $lineIds
     * @return array<int, int>
     */
    private function unitsGivenBack(array $lineIds): array
    {
        /** @var array<int, int> $given */
        $given = RefundLine::query()
            ->whereIn('order_line_id', $lineIds)
            ->whereHas('refund.paymentRefunds', static fn (Builder $parts) => $parts->whereIn('status', RefundStatus::holdingMoney()))
            ->groupBy('order_line_id')
            ->selectRaw('order_line_id, sum(quantity) as units')
            ->pluck('units', 'order_line_id')
            ->map(static fn (mixed $units): int => (int) $units)
            ->all();

        return $given;
    }

    /**
     * What units after the first $before of a line come to, from its amount split across all its units.
     */
    private function units(Money $amount, int $quantity, int $before, int $count): Money
    {
        $perUnit = $amount->allocate(array_fill(0, max(1, $quantity), 1));

        return Money::sum($amount->currency(), array_slice($perUnit, $before, $count));
    }
}
