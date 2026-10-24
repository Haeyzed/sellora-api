<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Tenant\Payments\Events\PaymentGatewayNeedsReconnecting;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Whether a connection needs the merchant to reconnect it, because the gateway refused its keys. Marked in one conditional update, so the merchant is alerted once, however many calls are refused at the same moment.
 */
final readonly class GatewayConnectionAttention
{
    public function keysRefused(PaymentGatewayConnection $connection): void
    {
        $now = CarbonImmutable::now();
        $isFirst = PaymentGatewayConnection::query()
            ->whereKey($connection->id)
            ->whereNull('needs_attention_at')
            ->update(['needs_attention_at' => $now, 'updated_at' => $now]) === 1;

        if (! $isFirst) {
            return;
        }

        $connection->needs_attention_at = $now;
        $connection->syncOriginalAttribute('needs_attention_at');
        // Only the gateway's key: never anything of the keys themselves.
        Log::warning('A payment gateway refused the store\'s keys; the merchant is asked to reconnect it.', ['gateway' => $connection->gateway]);
        PaymentGatewayNeedsReconnecting::dispatch($connection);
    }

    public function keysWork(PaymentGatewayConnection $connection): void
    {
        PaymentGatewayConnection::query()->whereKey($connection->id)->whereNotNull('needs_attention_at')->update(['needs_attention_at' => null]);
        $connection->needs_attention_at = null;
        $connection->syncOriginalAttribute('needs_attention_at');
    }
}
