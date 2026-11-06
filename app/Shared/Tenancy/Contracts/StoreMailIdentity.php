<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * Who a store's emails come from and where replies to them go, for every email sent while a store is the current one.
 *
 * Implemented by Tenant\Messaging, which owns the store's sender settings, so
 * Shared never imports a store domain (section 7).
 */
interface StoreMailIdentity
{
    /**
     * The name the store's emails come from, such as the store's own name.
     */
    public function senderName(): string;

    /**
     * Where replies go; null when the store has no address for them.
     */
    public function replyToAddress(): ?string;
}
