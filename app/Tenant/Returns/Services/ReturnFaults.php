<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Services;

use App\Tenant\Returns\Data\FaultConfirmation;
use App\Tenant\Returns\Exceptions\ReturnFaultReasonRequiredException;
use App\Tenant\Returns\Models\OrderReturn;

/**
 * Records whether the store was at fault for a return, as staff confirm it when they inspect its items or approve it without them coming back (Step 18d).
 *
 * Saying nothing confirms what the buyer's reason claims; deciding otherwise
 * needs a reason, kept with it. Only a confirmed fault gives a free
 * like-for-like replacement and free delivery in an exchange.
 */
final readonly class ReturnFaults
{
    /**
     * The fields to save with the inspection or approval.
     *
     * @return array{stores_fault: bool, fault_overruled_reason: string|null}
     *
     * @throws ReturnFaultReasonRequiredException When staff overrule the reason without saying why.
     */
    public function confirm(OrderReturn $return, FaultConfirmation $confirmation): array
    {
        $claimed = $return->reason->isStoresFault();
        $storesFault = $confirmation->storesFault ?? $claimed;
        $overruled = $storesFault !== $claimed;
        $reason = $confirmation->overruledReason === null ? null : trim($confirmation->overruledReason);

        if ($overruled && ($reason === null || $reason === '')) {
            throw new ReturnFaultReasonRequiredException;
        }

        return ['stores_fault' => $storesFault, 'fault_overruled_reason' => $overruled ? $reason : null];
    }
}
