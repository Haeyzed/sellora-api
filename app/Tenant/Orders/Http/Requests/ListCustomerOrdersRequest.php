<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Customers\Concerns\ActsAsCustomer;

/**
 * A page of the signed-in customer's own orders, newest first.
 */
final class ListCustomerOrdersRequest extends CursorListRequest
{
    use ActsAsCustomer;
}
