<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Enums;

/**
 * What staff can be allowed to do with returns. Refunding a return also needs payments.refund.
 */
enum ReturnPermission: string
{
    /** See returns, their photos and history, and the return settings. */
    case ReturnsView = 'returns.view';

    /** Start, approve, reject, receive, inspect and cancel returns, and change the return settings. */
    case ReturnsManage = 'returns.manage';
}
