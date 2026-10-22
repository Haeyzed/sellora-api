<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAccessToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use LogicException;

/**
 * Gives a guest's order its access token, for Checkout to show the guest once when the order is placed (Step 16). Only the hash is kept.
 */
final readonly class IssueOrderAccessToken
{
    /**
     * @return string The token, to show once.
     *
     * @throws LogicException When the order belongs to an account or already has a token: an account's orders are reached by signing in.
     */
    public function handle(Order $order): string
    {
        if ($order->customer_id !== null) {
            throw new LogicException('Only a guest\'s order gets an access token.');
        }

        $token = Str::random(64);

        $access = new OrderAccessToken;
        $access->forceFill([
            'order_id' => $order->id,
            'token_hash' => OrderAccessToken::hash($token),
            'expires_at' => CarbonImmutable::now()->addDays(config()->integer('orders.access_token_days')),
            'created_at' => CarbonImmutable::now(),
        ])->save();

        return $token;
    }
}
