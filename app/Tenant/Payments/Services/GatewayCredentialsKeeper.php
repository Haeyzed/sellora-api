<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Tenant\Payments\Enums\ConnectionStatus;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a gateway connection's keys exactly as long as money they took is in motion (section 3.3), then forgets them.
 *
 * A gateway disconnected while payments or refunds on it were in motion
 * keeps its keys and webhook endpoints, and keys replaced meanwhile are kept
 * as retired ones, so that money is still checked, refunded and told about.
 * Once it has all settled, they are forgotten here, on the hourly sweep.
 */
final readonly class GatewayCredentialsKeeper
{
    public function __construct(
        private MoneyInMotion $moneyInMotion,
        private WebhookEndpoints $webhookEndpoints,
    ) {}

    /**
     * Clears a connection's keys and webhook endpoints on the model (the caller saves it under its lock), returning the endpoint IDs to delete once that has committed.
     *
     * @return list<string>
     */
    public function forget(PaymentGatewayConnection $connection): array
    {
        $endpointIds = $connection->endpointIds();

        $connection->forceFill([
            'credentials' => null,
            'retired_credentials' => null,
            'credentials_hint' => null,
            'test_endpoint_id' => null,
            'live_endpoint_id' => null,
        ]);

        return $endpointIds;
    }

    /**
     * @return int How many connections forgot keys.
     */
    public function forgetSettled(): int
    {
        $ids = PaymentGatewayConnection::query()
            ->whereNotNull('credentials')
            ->where(static fn ($kept) => $kept->where('status', ConnectionStatus::Disconnected->value)->orWhereNotNull('retired_credentials'))
            ->orderBy('id')
            ->pluck('id');
        $forgot = 0;

        foreach ($ids as $id) {
            $forgot += $this->forgetIfSettled($id) ? 1 : 0;
        }

        return $forgot;
    }

    /**
     * Under the connection's lock, so a key replaced or a refund sent at the same moment is seen either way.
     */
    private function forgetIfSettled(int $id): bool
    {
        [$forgot, $endpointIds] = DB::transaction(function () use ($id): array {
            $connection = PaymentGatewayConnection::query()->whereKey($id)->lockForUpdate()->first();

            if ($connection === null || ! $connection->holdsCredentials() || $this->moneyInMotion->on($connection)) {
                return [false, []];
            }

            if ($connection->isConnected()) {
                if ($connection->retired_credentials === null) {
                    return [false, []];
                }

                $connection->forceFill(['retired_credentials' => null])->save();

                return [true, []];
            }

            $endpointIds = $this->forget($connection);
            $connection->save();

            return [true, $endpointIds];
        });

        foreach ($endpointIds as $endpointId) {
            $this->webhookEndpoints->delete($endpointId);
        }

        return $forgot;
    }
}
