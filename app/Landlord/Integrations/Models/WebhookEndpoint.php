<?php

declare(strict_types=1);

namespace App\Landlord\Integrations\Models;

use App\Landlord\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A random webhook endpoint ID leading to one store's connection with an integration.
 *
 * @property int $id
 * @property string $public_id Random, and the only part of the webhook URL that names it.
 * @property string $tenant_id
 * @property string $integration Such as "paystack".
 * @property CarbonImmutable $created_at
 */
final class WebhookEndpoint extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
