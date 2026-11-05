<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Data\DestinationRefund;
use App\Tenant\Payments\Enums\RefundReviewReason;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Events\RefundDestinationFailed;
use App\Tenant\Payments\Events\RefundGivenToDestination;
use App\Tenant\Payments\Exceptions\RefundDestinationRefusedException;
use App\Tenant\Payments\Jobs\GiveRefundToDestinationAgain;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Payments\RefundDestinations;
use App\Tenant\Payments\Services\RefundSettlement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Pays a refund out to the module's destination the customer chose, such as store credit (section 13), never through a gateway. Called outside any transaction, after the refund is saved.
 *
 * Each ask is first claimed in one conditional update, so of two callers at
 * the same moment only one asks the module; a module must still treat a
 * refund it already paid out as done, since an ask whose answer was lost is
 * asked again. The module's answer settles every part of the refund at once:
 * paid out, each part goes back (and gets its credit note, since the sale is
 * reduced); definitely refused, each part fails, and the customer can choose
 * again where it goes. A destination no module registers any more, or one
 * whose module is unavailable to the store, is a definite failure too, never
 * a crash. Any other error is an unknown outcome: asked again after each wait
 * in config payments.destinations.retry_minutes, then sent to staff review.
 * Never falls back to a gateway: the customer chose where it goes. Paid out
 * whatever state the module is in, except Unavailable (section 3.3).
 */
final readonly class GiveRefundToDestination
{
    public const string NOT_REGISTERED = 'destination_not_registered';

    public const string UNAVAILABLE = 'destination_unavailable';

    public function __construct(
        private RefundDestinations $refundDestinations,
        private RefundSettlement $refundSettlement,
    ) {}

    public function handle(Refund $refund): Refund
    {
        if (! $refund->goesToDestination()) {
            return $refund;
        }

        $open = $this->openParts($refund);
        $claimed = $open->first();

        if ($claimed === null || ! $this->claim($claimed)) {
            return $refund;
        }

        $destination = $this->refundDestinations->forPayout($refund->destination);

        if ($destination === null) {
            $this->fail($refund, $open, $this->refundDestinations->isRegistered($refund->destination) ? self::UNAVAILABLE : self::NOT_REGISTERED);

            return $refund->refresh();
        }

        try {
            $reference = $destination->giveBack($this->payout($refund));
        } catch (RefundDestinationRefusedException $exception) {
            $this->fail($refund, $open, $exception->errorCode());

            return $refund->refresh();
        } catch (Throwable $exception) {
            $this->afterUnknownOutcome($refund, $open, $claimed->refresh(), $exception);

            return $refund->refresh();
        }

        foreach ($open as $part) {
            $this->refundSettlement->succeedAtDestination($part, $reference);
        }

        RefundGivenToDestination::dispatch($refund->refresh());

        return $refund;
    }

    /**
     * Its parts still waiting for the module's answer: pending, and not in staff review.
     *
     * @return Collection<int, PaymentRefund>
     */
    private function openParts(Refund $refund): Collection
    {
        return PaymentRefund::query()->where('refund_id', $refund->id)->where('status', RefundStatus::Pending->value)->whereNull('needs_review_at')->orderBy('id')->get();
    }

    /**
     * Claims this ask in one conditional update: only one caller can win while the last ask is newer than config payments.destinations.claim_minutes.
     */
    private function claim(PaymentRefund $part): bool
    {
        $now = CarbonImmutable::now();

        return PaymentRefund::query()
            ->whereKey($part->id)
            ->where('status', RefundStatus::Pending->value)
            ->whereNull('needs_review_at')
            ->where(static fn ($quiet) => $quiet->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', $now->subMinutes(config()->integer('payments.destinations.claim_minutes'))))
            ->update(['last_checked_at' => $now, 'checks' => $part->checks + 1, 'updated_at' => $now]) === 1;
    }

    /**
     * @throws LogicException When the refund's order has no customer: a refund only goes to a destination for the order's own customer.
     */
    private function payout(Refund $refund): DestinationRefund
    {
        $order = $refund->order()->with('customer')->firstOrFail();
        $customer = $order->customer ?? throw new LogicException("Refund {$refund->public_id} goes to a destination, but its order has no customer.");

        return new DestinationRefund($refund->public_id, $order->public_id, $customer->public_id, $refund->amount, $order->is_test);
    }

    /**
     * @param  Collection<int, PaymentRefund>  $open
     */
    private function fail(Refund $refund, Collection $open, string $code): void
    {
        foreach ($open as $part) {
            $this->refundSettlement->refuse($part, $code);
        }

        // Only public IDs: staff find the rest from there.
        Log::warning('A refund to a destination definitely failed; nothing was paid out.', ['refund' => $refund->public_id, 'destination' => $refund->destination, 'code' => $code]);
        RefundDestinationFailed::dispatch($refund, $code);
    }

    /**
     * The module may or may not have paid it out: asked again after the next wait, or once the waits run out, sent to staff review and never asked again.
     *
     * @param  Collection<int, PaymentRefund>  $open
     */
    private function afterUnknownOutcome(Refund $refund, Collection $open, PaymentRefund $claimed, Throwable $exception): void
    {
        report($exception);
        /** @var list<int> $waits */
        $waits = config()->array('payments.destinations.retry_minutes');
        $wait = $waits[$claimed->checks - 1] ?? null;

        if ($wait === null) {
            foreach ($open as $part) {
                $this->refundSettlement->sendToReview($part, RefundReviewReason::DestinationUnclear);
            }

            return;
        }

        // Only public IDs: staff find the rest from there.
        Log::warning('A refund destination didn\'t say whether it paid a refund out; it is asked again later.', ['refund' => $refund->public_id, 'destination' => $refund->destination, 'ask' => $claimed->checks]);
        GiveRefundToDestinationAgain::dispatch($refund->id)->delay(CarbonImmutable::now()->addMinutes($wait));
    }
}
