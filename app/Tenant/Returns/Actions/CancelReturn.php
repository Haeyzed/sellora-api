<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Tenant\Returns\Data\ReturnRequester;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Services\ReturnProgress;
use Illuminate\Support\Facades\DB;

/**
 * Calls a return off: its buyer while it is still requested, or staff before the items arrive (requested or approved). Its units can then be returned again. ReturnCancelled is announced once it is saved.
 *
 * Checked on the return as saved under its lock, so a cancellation and an
 * approval or receipt at the same moment never both happen.
 */
final readonly class CancelReturn
{
    public function __construct(private ReturnProgress $returnProgress) {}

    /**
     * @throws ReturnTransitionNotAllowedException When it can't be cancelled from where it stands, or not by this requester.
     */
    public function handle(OrderReturn $return, ReturnRequester $requester): OrderReturn
    {
        return DB::transaction(function () use ($return, $requester): OrderReturn {
            $locked = $this->returnProgress->lock($return);
            $allowed = $requester->isStaff ? [ReturnStatus::Requested, ReturnStatus::Approved] : [ReturnStatus::Requested];

            if (! in_array($locked->status, $allowed, true)) {
                throw new ReturnTransitionNotAllowedException;
            }

            return $this->returnProgress->move($locked, ReturnStatus::Cancelled, $requester->account);
        });
    }
}
