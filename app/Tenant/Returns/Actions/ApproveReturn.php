<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Services\ReturnProgress;
use Illuminate\Support\Facades\DB;

/**
 * Staff agreeing to a requested return: the items are to come back, or, when the buyer keeps them, it is refunded without receiving or inspecting anything and no stock moves. ReturnApproved is announced once it is saved.
 *
 * Checked on the return as saved under its lock, so two approvals, or an
 * approval and a cancellation, at the same moment never both happen. Whoever
 * lets the buyer keep the items needs to be able to refund too; the request
 * checks that.
 */
final readonly class ApproveReturn
{
    public function __construct(private ReturnProgress $returnProgress) {}

    /**
     * @throws ReturnTransitionNotAllowedException When it isn't requested any more.
     */
    public function handle(OrderReturn $return, AccountReference $by, bool $keepItems = false): OrderReturn
    {
        return DB::transaction(function () use ($return, $by, $keepItems): OrderReturn {
            return $this->returnProgress->move($this->returnProgress->lock($return), ReturnStatus::Approved, $by, ['keep_items' => $keepItems]);
        });
    }
}
