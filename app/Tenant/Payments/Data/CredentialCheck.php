<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Tenant\Payments\Enums\GatewayMode;

/**
 * What the gateway said about a store's credentials: whether they work, whether they are test or live keys, and the account they belong to when it can tell.
 */
final readonly class CredentialCheck
{
    /**
     * @param  string|null  $accountId  The gateway's own ID for the account the keys belong to; null when it can't tell.
     * @param  string|null  $hint  A harmless reminder of which keys these are, such as the last four characters of the public key.
     */
    public function __construct(
        public bool $isValid,
        public GatewayMode $mode,
        public ?string $accountId = null,
        public ?string $hint = null,
    ) {}
}
