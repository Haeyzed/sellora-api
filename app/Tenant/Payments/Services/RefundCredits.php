<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Shared\Money\Money;
use App\Tenant\Orders\Contracts\RefundsAwaitingCreditNotes;
use App\Tenant\Orders\Data\CreditedLine;
use App\Tenant\Orders\Data\CreditRequest;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Payments\Enums\RefundPurpose;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\Refund;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * What each part of a refund that reduced the sale credits, for Orders to issue its credit note.
 *
 * A refund's lines, shipping and any price reduction are shared across its
 * payment parts in order: each part's amount is split across what is still
 * left of them by largest remainder, and their units go with it, so each
 * part's credit note adds up to exactly what that part gave back, and all of
 * them to the whole refund, whichever part goes back first. Orders works out
 * the tax. A refund that returned money never part of the sale credits
 * nothing.
 */
final readonly class RefundCredits implements RefundsAwaitingCreditNotes
{
    /**
     * @return CreditRequest|null Null when the part credits nothing: its refund didn't reduce the sale, or it hasn't gone back.
     */
    public function requestFor(PaymentRefund $paymentRefund): ?CreditRequest
    {
        $refund = Refund::query()->with(['lines', 'paymentRefunds'])->findOrFail($paymentRefund->refund_id);
        $part = $refund->paymentRefunds->firstWhere('id', $paymentRefund->id);

        if ($refund->purpose !== RefundPurpose::ReducesSale || $part === null || $part->status !== RefundStatus::Succeeded) {
            return null;
        }

        $share = $this->shareOf($refund, $part);
        $lines = [];

        foreach ($refund->lines as $line) {
            [$amount, $quantity] = $share['line-'.$line->id];
            $lines[] = new CreditedLine($line->order_line_id, $quantity, $amount);
        }

        return new CreditRequest($refund->order_id, $part->public_id, $refund->public_id, $part->amount, $lines, $share['shipping'][0], $share['adjustment'][0]);
    }

    public function wentBackBefore(CarbonImmutable $time, int $limit): array
    {
        return array_values(array_filter(PaymentRefund::query()
            ->where('status', RefundStatus::Succeeded->value)
            ->where('succeeded_at', '<=', $time)
            // Only orders that were sold have an invoice to credit; any other would come back every run.
            ->whereHas('refund', static fn (Builder $refunds): Builder => $refunds->where('purpose', RefundPurpose::ReducesSale->value)
                ->whereExists(static fn (QueryBuilder $paid): QueryBuilder => $paid->select(DB::raw(1))->from('order_events')->whereColumn('order_events.order_id', 'refunds.order_id')->where('order_events.type', OrderEventType::Paid->value)))
            ->whereNotExists(static fn (QueryBuilder $creditNotes): QueryBuilder => $creditNotes->select(DB::raw(1))->from('credit_notes')->whereColumn('credit_notes.reference', 'payment_refunds.public_id'))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (PaymentRefund $paymentRefund): ?CreditRequest => $this->requestFor($paymentRefund))
            ->all()));
    }

    /**
     * The part's share of each of the refund's lines, its shipping and any price reduction (keyed "line-{id}", "shipping" and "adjustment"): the amount and its units.
     *
     * @return array<string, array{Money, int}>
     */
    private function shareOf(Refund $refund, PaymentRefund $part): array
    {
        $currency = $refund->currency;
        /** @var array<string, array{int, int}> $left Amount and units still to share out, by component. */
        $left = [];

        foreach ($refund->lines as $line) {
            $left['line-'.$line->id] = [$line->amount->minorAmount(), $line->quantity];
        }

        $left['shipping'] = [$refund->shipping->minorAmount(), 1];
        $left['adjustment'] = [$refund->amount->minorAmount() - array_sum(array_column($left, 0)), 1];

        foreach ($refund->paymentRefunds as $earlier) {
            $share = [];

            foreach ($earlier->amount->allocate(array_map(static fn (array $component): int => $component[0], $left)) as $key => $amount) {
                [$amountLeft, $unitsLeft] = $left[$key];
                // Units go with the amount, the last part taking what is left.
                $units = $this->sameShare($unitsLeft, $amount->minorAmount(), $amountLeft, $currency);
                $share[$key] = [$amount, $units];
                $left[$key] = [$amountLeft - $amount->minorAmount(), $unitsLeft - $units];
            }

            if ($earlier->id === $part->id) {
                return $share;
            }
        }

        throw new LogicException('A payment refund is not part of its own refund.');
    }

    /**
     * The share of a line's units that a share is of its amount, by largest remainder: all of them when the share is all of the amount.
     */
    private function sameShare(int $whole, int $share, int $of, string $currency): int
    {
        if ($of === 0 || $whole === 0) {
            return 0;
        }

        return Money::ofMinor($whole, $currency)->allocate([$share, $of - $share])[0]->minorAmount();
    }
}
