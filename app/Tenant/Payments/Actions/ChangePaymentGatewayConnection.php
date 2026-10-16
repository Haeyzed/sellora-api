<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotConnectedException;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\Services\PaymentActivity;
use Illuminate\Support\Facades\DB;

/**
 * Turns a connected gateway on or off for new payments, or changes the order gateways are offered in. Allowed whatever the plan: turning one off never stops money already in motion.
 */
final readonly class ChangePaymentGatewayConnection
{
    public function __construct(private PaymentActivity $paymentActivity) {}

    /**
     * @throws PaymentGatewayNotConnectedException
     */
    public function handle(StaffMember $actor, string $gatewayKey, ?bool $enabled, ?int $priority): PaymentGatewayConnection
    {
        $connection = DB::transaction(static function () use ($gatewayKey, $enabled, $priority): PaymentGatewayConnection {
            $connection = PaymentGatewayConnection::query()->where('gateway', $gatewayKey)->lockForUpdate()->first();

            if ($connection === null || ! $connection->isConnected()) {
                throw new PaymentGatewayNotConnectedException;
            }

            if ($enabled !== null) {
                $connection->enabled = $enabled;
            }

            if ($priority !== null) {
                $connection->priority = $priority;
            }

            $connection->save();

            return $connection;
        });

        $this->paymentActivity->record($actor, $connection, 'payment_gateway_changed', 'Changed how the payment gateway '.$gatewayKey.' is offered', array_filter(['enabled' => $enabled, 'priority' => $priority], static fn (bool|int|null $value): bool => $value !== null));

        return $connection;
    }
}
