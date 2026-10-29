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
 * Staff refusing a return, with a reason: while it is requested, or once its items were inspected and nothing is owed (such as damage the buyer caused). Its units can then be asked for again. ReturnRejected is announced once it is saved.
 *
 * Stock already moved at inspection stays where it went: what came back is
 * at the store whatever is refunded.
 */
final readonly class RejectReturn
{
    public function __construct(private ReturnProgress $returnProgress) {}

    /**
     * @throws ReturnTransitionNotAllowedException When it is neither requested nor inspected.
     */
    public function handle(OrderReturn $return, string $reason, AccountReference $by): OrderReturn
    {
        return DB::transaction(function () use ($return, $reason, $by): OrderReturn {
            return $this->returnProgress->move($this->returnProgress->lock($return), ReturnStatus::Rejected, $by, ['rejection_reason' => $reason]);
        });
    }
}
