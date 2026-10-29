<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Actions\SendReturnedUnitsBack;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Exceptions\ReturnedUnitsNoLongerOnHandException;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnLine;
use App\Tenant\Returns\Services\ReturnProgress;
use App\Tenant\Returns\Services\ReturnRefundAttempts;
use Illuminate\Support\Facades\DB;

/**
 * Staff refusing a return, with a reason: while it is requested, or once its items were inspected and nothing is owed (such as damage the buyer caused), when they go back to the buyer. Its units can then be asked for again. ReturnRejected is announced once it is saved.
 *
 * Rejecting an inspected return sends its units back through Inventory in
 * the same transaction, as new movements with the return as their source, so
 * on hand ends where it was before the inspection; if restocked units were
 * sold or held since, nothing changes and it is refused. A return whose
 * refund may have given money back is never rejected.
 */
final readonly class RejectReturn
{
    public function __construct(
        private ReturnProgress $returnProgress,
        private ReturnRefundAttempts $attempts,
        private SendReturnedUnitsBack $sendReturnedUnitsBack,
    ) {}

    /**
     * @throws ReturnTransitionNotAllowedException When it is neither requested nor inspected, or its refund may have given money back.
     * @throws ReturnedUnitsNoLongerOnHandException
     */
    public function handle(OrderReturn $return, string $reason, AccountReference $by): OrderReturn
    {
        return DB::transaction(function () use ($return, $reason, $by): OrderReturn {
            $locked = $this->returnProgress->lock($return);

            if (! $locked->status->canMoveTo(ReturnStatus::Rejected) || $this->attempts->mayHaveRefunded($locked)) {
                throw new ReturnTransitionNotAllowedException;
            }

            if ($locked->status === ReturnStatus::Inspected) {
                $this->sendUnitsBack($locked, $by);
            }

            return $this->returnProgress->move($locked, ReturnStatus::Rejected, $by, ['rejection_reason' => $reason]);
        });
    }

    /**
     * @throws ReturnedUnitsNoLongerOnHandException
     */
    private function sendUnitsBack(OrderReturn $locked, AccountReference $by): void
    {
        $restocked = [];
        $writtenOff = [];

        foreach ($locked->lines()->with('orderLine')->get() as $line) {
            /** @var ReturnLine $line */
            $variant = $line->orderLine->product_variant_id;
            $restocked[$variant] = ($restocked[$variant] ?? 0) + (int) $line->restocked_quantity;
            $writtenOff[$variant] = ($writtenOff[$variant] ?? 0) + (int) $line->written_off_quantity;
        }

        $location = StockLocation::query()->whereKey($locked->stock_location_id)->firstOrFail();
        $this->sendReturnedUnitsBack->handle(StockSource::of($locked), $location, $restocked, $writtenOff, $by);
    }
}
