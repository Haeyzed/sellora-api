<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Data;

/**
 * Staff's word on whether the store was at fault for a return (Step 18d), given when they inspect its items or approve it without them coming back.
 *
 * The buyer's reason is only a claim: staff confirm it by saying nothing,
 * or overrule it, saying why.
 */
final readonly class FaultConfirmation
{
    /**
     * @param  bool|null  $storesFault  Whether the store was at fault; null to confirm what the reason claims.
     * @param  string|null  $overruledReason  Why, when it differs from what the reason claims; required then, and ignored otherwise.
     */
    public function __construct(
        public ?bool $storesFault = null,
        public ?string $overruledReason = null,
    ) {}

    /**
     * Confirms what the buyer's reason claims.
     */
    public static function asClaimed(): self
    {
        return new self;
    }
}
