<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Returns\Data\FaultConfirmation;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\ReturnFaultReasonRequiredException;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Services\ReturnFaults;
use App\Tenant\Returns\Services\ReturnProgress;
use Illuminate\Support\Facades\DB;

/**
 * Staff agreeing to a requested return: the items are to come back, or, when the buyer keeps them, it is settled without receiving or inspecting anything and no stock moves, and whether the store was at fault is confirmed now. ReturnApproved is announced once it is saved.
 *
 * Checked on the return as saved under its lock, so two approvals, or an
 * approval and a cancellation, at the same moment never both happen. Whoever
 * lets the buyer keep the items needs to be able to refund too; the request
 * checks that.
 */
final readonly class ApproveReturn
{
    public function __construct(
        private ReturnProgress $returnProgress,
        private ReturnFaults $returnFaults,
    ) {}

    /**
     * @param  FaultConfirmation|null  $fault  When the buyer keeps the items: whether the store was at fault; null confirms what the reason claims. Ignored otherwise, as it is confirmed at inspection.
     *
     * @throws ReturnTransitionNotAllowedException When it isn't requested any more.
     * @throws ReturnFaultReasonRequiredException When the reason is overruled without saying why.
     */
    public function handle(OrderReturn $return, AccountReference $by, bool $keepItems = false, ?FaultConfirmation $fault = null): OrderReturn
    {
        return DB::transaction(function () use ($return, $by, $keepItems, $fault): OrderReturn {
            $locked = $this->returnProgress->lock($return);
            // Nothing comes back to inspect, so the fault is confirmed now.
            $faultFields = $keepItems ? $this->returnFaults->confirm($locked, $fault ?? FaultConfirmation::asClaimed()) : [];

            return $this->returnProgress->move($locked, ReturnStatus::Approved, $by, ['keep_items' => $keepItems, ...$faultFields]);
        });
    }
}
