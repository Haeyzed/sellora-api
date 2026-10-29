<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when refunding a return would need money a module's payment method holds for the order (such as a gift card), which today it gives back only in full when the whole order is cancelled. Staff can refund what was paid otherwise by amount from the order's payments.
 */
final class ReturnRefundModuleHeldException extends DomainException
{
    public function errorCode(): string
    {
        return 'return_refund_module_held';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
