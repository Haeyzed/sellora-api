<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Enums;

use App\Shared\Auth\Contracts\GrantablePermission;

/**
 * What staff can be allowed to do with returns. Refunding a return, or an exchange's difference, also needs payments.refund.
 */
enum ReturnPermission: string implements GrantablePermission
{
    /** See returns, their photos and history, and the return settings. */
    case ReturnsView = 'returns.view';

    /** Start, approve, reject, receive, inspect and cancel returns, and change the return settings. */
    case ReturnsManage = 'returns.manage';

    /** Confirm a return's exchange, which places its replacement order and settles what the returned items are worth (Step 18d). */
    case ReturnsExchange = 'returns.exchange';

    public function group(): string
    {
        return 'returns';
    }

    /**
     * Confirming an exchange places an order and moves money, so dashboards warn before it is granted.
     */
    public function isSensitive(): bool
    {
        return $this === self::ReturnsExchange;
    }
}
