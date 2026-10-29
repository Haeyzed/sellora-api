<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Orders\Concerns\ActsAsGuestOrderHolder;

/**
 * A page of a guest's order's returns, newest first, with the order's access token in the X-Order-Token header. Without it, the order is not found (404).
 */
final class ListGuestReturnsRequest extends CursorListRequest
{
    use ActsAsGuestOrderHolder;
}
