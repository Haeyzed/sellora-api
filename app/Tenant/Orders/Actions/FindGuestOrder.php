<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAccessToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * A guest reaching their order with the access token they were given when it was placed (Step 16). Without the order's own token, unexpired, the order is not found (404), never forbidden, so trying IDs or tokens reveals nothing.
 */
final readonly class FindGuestOrder
{
    /**
     * @param  string|null  $token  From the X-Order-Token header only: never a URL, which ends up in logs and browser history.
     *
     * @throws ModelNotFoundException When the token is missing, expired or not this order's.
     */
    public function handle(Order $order, ?string $token): Order
    {
        $valid = $token !== null
            && OrderAccessToken::isWellFormed($token)
            && OrderAccessToken::query()
                ->where('order_id', $order->id)
                ->where('token_hash', OrderAccessToken::hash($token))
                ->where('expires_at', '>', CarbonImmutable::now())
                ->exists();

        if (! $valid) {
            throw (new ModelNotFoundException)->setModel(Order::class, [$order->public_id]);
        }

        return $order;
    }
}
