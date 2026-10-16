<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Payments\Data\GatewayCredentials;
use App\Tenant\Payments\Enums\ConnectionStatus;
use App\Tenant\Payments\Enums\GatewayMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * The store's own account with one payment gateway: its encrypted credentials, whether it is offered for new payments, and in what order.
 *
 * Credentials are never returned by the API, never audited and never
 * exported; only a harmless hint (such as the last four characters of the
 * public key) is shown.
 *
 * @property int $id
 * @property string $public_id
 * @property string $gateway The gateway integration key, such as "paystack".
 * @property ConnectionStatus $status
 * @property bool $enabled Offered for new payments; turning it off never stops money already in motion.
 * @property int $priority Lower is offered first.
 * @property GatewayMode|null $mode
 * @property array<string, string>|null $credentials Decrypted; null once disconnected.
 * @property string|null $credentials_hint
 * @property string|null $account_id The gateway account the keys belong to, when it can tell.
 * @property string|null $endpoint_id Its webhook endpoint in Landlord\Integrations.
 * @property CarbonImmutable|null $connected_at
 * @property CarbonImmutable|null $checked_at
 * @property CarbonImmutable|null $disconnected_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class PaymentGatewayConnection extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * Credentials never go into audits; the endpoint ID stays out too.
     *
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id', 'credentials', 'endpoint_id', 'updated_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['credentials', 'endpoint_id'];

    public function isConnected(): bool
    {
        return $this->status === ConnectionStatus::Connected;
    }

    /**
     * The decrypted credentials for a gateway call.
     */
    public function gatewayCredentials(): GatewayCredentials
    {
        return new GatewayCredentials($this->credentials ?? []);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
            'enabled' => 'boolean',
            'priority' => 'integer',
            'mode' => GatewayMode::class,
            'credentials' => 'encrypted:array',
            'connected_at' => 'immutable_datetime',
            'checked_at' => 'immutable_datetime',
            'disconnected_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
