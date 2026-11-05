<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Tenant\Returns\Enums\ReturnResolution;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\ReturnReplacementInvalidException;
use App\Tenant\Returns\Exceptions\ReturnReplacementNotAvailableException;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Services\ReturnProgress;
use App\Tenant\Returns\Services\ReturnReplacements;
use Illuminate\Support\Facades\DB;

/**
 * Staff choosing other variants to send in an exchange (Step 18d), such as another size when the one asked for sold out: one for every returned line, each for sale and in stock (or taking backorders). No stock is held for them.
 *
 * Only until the exchange is confirmed: never once its replacement order is
 * placed, or the return is finished.
 */
final readonly class ChangeReturnReplacements
{
    public function __construct(
        private ReturnProgress $returnProgress,
        private ReturnReplacements $returnReplacements,
    ) {}

    /**
     * @param  array<string, string>  $replacements  Variant public IDs, by order line public ID: every line of the return.
     *
     * @throws ReturnTransitionNotAllowedException When it isn't an exchange, or is no longer open to changes.
     * @throws ReturnReplacementInvalidException
     * @throws ReturnReplacementNotAvailableException
     */
    public function handle(OrderReturn $return, array $replacements): OrderReturn
    {
        return DB::transaction(function () use ($return, $replacements): OrderReturn {
            $locked = $this->returnProgress->lock($return);

            if ($locked->resolution !== ReturnResolution::Exchange || ! self::isOpenToChange($locked)) {
                throw new ReturnTransitionNotAllowedException;
            }

            $lines = $this->returnReplacements->linesOf($locked);
            $variants = $this->returnReplacements->resolve($lines, ReturnResolution::Exchange, $replacements);
            $this->returnReplacements->ensureAvailable($variants);
            $this->returnReplacements->save($locked, $variants);

            return $locked->refresh();
        });
    }

    /**
     * Whether its resolution and replacements may still change: before its exchange is confirmed, and while it isn't finished.
     */
    public static function isOpenToChange(OrderReturn $return): bool
    {
        return in_array($return->status, [ReturnStatus::Requested, ReturnStatus::Approved, ReturnStatus::Received, ReturnStatus::Inspected], true);
    }
}
