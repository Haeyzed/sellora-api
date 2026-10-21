<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Where the central relay page sends a provider's answer to a customer social sign-in (Step 10b): the store that started it, found by the hash of its one-time state.
 *
 * Routing only: the provider, the store and when it expires. Nothing about
 * the customer is ever stored here; that stays in the store's database.
 *
 * @property int $id
 * @property string $state_hash
 * @property string $tenant_id
 * @property string $provider
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at When the relay sent it on; it never does twice.
 * @property CarbonImmutable $created_at
 * @property-read Tenant $tenant
 */
final class SocialSignInState extends Model
{
    use CentralConnection;

    public const null UPDATED_AT = null;

    protected $hidden = [
        'state_hash',
    ];

    /**
     * The value stored for a state.
     */
    public static function hash(string $state): string
    {
        return hash('sha256', $state);
    }

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
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
