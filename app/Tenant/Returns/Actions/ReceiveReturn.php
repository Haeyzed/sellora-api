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
 * Staff recording that an approved return's items arrived at the store. Nothing moves in stock until they are inspected. ReturnReceived is announced once it is saved.
 */
final readonly class ReceiveReturn
{
    public function __construct(private ReturnProgress $returnProgress) {}

    /**
     * @throws ReturnTransitionNotAllowedException When it isn't approved, or the buyer keeps the items.
     */
    public function handle(OrderReturn $return, AccountReference $by): OrderReturn
    {
        return DB::transaction(function () use ($return, $by): OrderReturn {
            $locked = $this->returnProgress->lock($return);

            if ($locked->keep_items) {
                throw new ReturnTransitionNotAllowedException;
            }

            return $this->returnProgress->move($locked, ReturnStatus::Received, $by);
        });
    }
}
