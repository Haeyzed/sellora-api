<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Returns\Enums\ReturnResolution;
use App\Tenant\Returns\Exceptions\ReturnReplacementInvalidException;
use App\Tenant\Returns\Exceptions\ReturnReplacementNotAvailableException;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Services\ReturnProgress;
use App\Tenant\Returns\Services\ReturnRefundAttempts;
use App\Tenant\Returns\Services\ReturnReplacements;
use Illuminate\Support\Facades\DB;

/**
 * Staff changing how a return is settled (Step 18d): an exchange made a refund (such as when no replacement can be sent, or the order's currency is no longer offered), its replacements cleared; or a refund made an exchange, naming a replacement for every line.
 *
 * Only before its exchange is confirmed, and never once a refund of it may
 * have given money back. The change is recorded in the store's activity.
 */
final readonly class ChangeReturnResolution
{
    public function __construct(
        private ReturnProgress $returnProgress,
        private ReturnReplacements $returnReplacements,
        private ReturnRefundAttempts $attempts,
    ) {}

    /**
     * @param  array<string, string>  $replacements  For an exchange: variant public IDs, by order line public ID, for every line; none for a refund.
     *
     * @throws ReturnTransitionNotAllowedException When it is no longer open to changes, or a refund of it may have given money back.
     * @throws ReturnReplacementInvalidException
     * @throws ReturnReplacementNotAvailableException
     */
    public function handle(OrderReturn $return, ReturnResolution $resolution, array $replacements, StaffMember $actor): OrderReturn
    {
        return DB::transaction(function () use ($return, $resolution, $replacements, $actor): OrderReturn {
            $locked = $this->returnProgress->lock($return);

            if (! ChangeReturnReplacements::isOpenToChange($locked) || $this->attempts->mayHaveRefunded($locked)) {
                throw new ReturnTransitionNotAllowedException;
            }

            $lines = $this->returnReplacements->linesOf($locked);
            $variants = $this->returnReplacements->resolve($lines, $resolution, $replacements);
            $this->returnReplacements->ensureAvailable($variants);

            $from = $locked->resolution;
            $locked->forceFill(['resolution' => $resolution])->save();
            $this->returnReplacements->save($locked, $variants);

            if ($from !== $resolution) {
                activity('returns')
                    ->causedBy($actor)
                    ->performedOn($locked)
                    ->event('return_resolution_changed')
                    ->withProperties(['from' => $from->value, 'to' => $resolution->value])
                    ->log('Changed how return '.$locked->displayNumber().' is settled, from '.$from->value.' to '.$resolution->value);
            }

            return $locked->refresh();
        });
    }
}
