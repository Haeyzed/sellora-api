<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Concerns;

use App\Tenant\Orders\Actions\FindGuestOrder;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAccessToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A guest acting on their order with its access token, for form requests in any domain (public surface, section 6). The token is read from the X-Order-Token header only; one in the query string is never looked at.
 *
 * @mixin FormRequest
 */
trait ActsAsGuestOrderHolder
{
    /**
     * @throws ModelNotFoundException When the request doesn't carry this order's unexpired token.
     */
    public function guestOrder(Order $order): Order
    {
        $token = $this->headers->get(OrderAccessToken::HEADER);

        return app(FindGuestOrder::class)->handle($order, is_string($token) ? $token : null);
    }
}
