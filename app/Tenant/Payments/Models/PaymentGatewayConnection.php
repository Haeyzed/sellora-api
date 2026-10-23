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
 * public key) is shown. Keys that took money still in motion are kept until
 * it settles (section 3.3): through a disconnect, and as retired keys when
 * replaced. It has a webhook endpoint per mode, as gateways' dashboards take
 * a webhook URL per mode.
 *
 * @property int $id
 * @property string $public_id
 * @property string $gateway The gateway integration key, such as "paystack".
 * @property ConnectionStatus $status
 * @property bool $enabled Offered for new payments; turning it off never stops money already in motion.
 * @property int $priority Lower is offered first.
 * @property GatewayMode|null $mode
 * @property array<string, string>|null $credentials Decrypted; null once disconnected and every payment and refund it took has settled.
 * @property list<array<string, string>>|null $retired_credentials Decrypted; earlier keys, kept while money they took is still in motion.
 * @property string|null $credentials_hint
 * @property string|null $account_id The gateway account the keys belong to, when it can tell.
 * @property string|null $test_endpoint_id Its webhook endpoint for test mode in Landlord\Integrations.
 * @property string|null $live_endpoint_id Its webhook endpoint for live mode in Landlord\Integrations.
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
     * Credentials never go into audits; the endpoint IDs stay out too.
     *
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id', 'credentials', 'retired_credentials', 'test_endpoint_id', 'live_endpoint_id', 'updated_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['credentials', 'retired_credentials', 'test_endpoint_id', 'live_endpoint_id'];

    public function isConnected(): bool
    {
        return $this->status === ConnectionStatus::Connected;
    }

    /**
     * Whether it still has keys: connected, or disconnected while money it took is still in motion. Money in motion is looked after with them either way.
     */
    public function holdsCredentials(): bool
    {
        return $this->credentials !== null;
    }

    /**
     * The decrypted credentials for a gateway call.
     */
    public function gatewayCredentials(): GatewayCredentials
    {
        return new GatewayCredentials($this->credentials ?? []);
    }

    /**
     * The current keys, then any retired ones: a webhook signed with keys replaced while money they took was in motion still checks out.
     *
     * @return list<GatewayCredentials>
     */
    public function webhookCredentials(): array
    {
        $all = $this->credentials === null ? [] : [$this->credentials];

        foreach ($this->retired_credentials ?? [] as $retired) {
            $all[] = $retired;
        }

        return array_map(static fn (array $credentials): GatewayCredentials => new GatewayCredentials($credentials), $all);
    }

    public function endpointFor(GatewayMode $mode): ?string
    {
        return $mode === GatewayMode::Live ? $this->live_endpoint_id : $this->test_endpoint_id;
    }

    /**
     * The mode a webhook endpoint of this connection serves; null when it is not one of its endpoints.
     */
    public function modeOfEndpoint(string $endpointId): ?GatewayMode
    {
        return match ($endpointId) {
            $this->test_endpoint_id => GatewayMode::Test,
            $this->live_endpoint_id => GatewayMode::Live,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function endpointIds(): array
    {
        return array_values(array_filter([$this->test_endpoint_id, $this->live_endpoint_id], static fn (?string $id): bool => $id !== null));
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
            'retired_credentials' => 'encrypted:array',
            'connected_at' => 'immutable_datetime',
            'checked_at' => 'immutable_datetime',
            'disconnected_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
