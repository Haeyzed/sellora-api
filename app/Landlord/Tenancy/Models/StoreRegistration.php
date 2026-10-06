<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Models;

use App\Shared\Concerns\HasNormalisedEmail;
use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A merchant's sign-up, waiting for them to enter the code emailed to them. No store or database exists until they do.
 *
 * Holds the owner's password hash only until their account exists in the
 * new store; then it is wiped.
 *
 * @property int $id
 * @property string $public_id
 * @property string $store_name
 * @property string $subdomain
 * @property string $owner_name
 * @property string $email
 * @property string|null $password_hash
 * @property string $country_code
 * @property string $timezone
 * @property string $hosting_region
 * @property string $verification_code_hash
 * @property int $verification_attempts
 * @property CarbonImmutable $verification_code_sent_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $verified_at
 * @property string|null $tenant_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Tenant|null $tenant
 */
final class StoreRegistration extends Model
{
    use CentralConnection;
    use HasNormalisedEmail;
    use HasPublicId;

    /** Wrong codes allowed before a new code has to be requested. */
    public const int MAX_VERIFICATION_ATTEMPTS = 5;

    protected $fillable = [
        'store_name',
        'subdomain',
        'owner_name',
        'email',
        'country_code',
        'timezone',
        'hosting_region',
    ];

    protected $hidden = [
        'password_hash',
        'verification_code_hash',
    ];

    /**
     * Whether it is still waiting for its code: not verified and not expired.
     */
    public function isAwaitingVerification(): bool
    {
        return $this->verified_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Sign-ups still waiting for their code.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeAwaitingVerification(Builder $query): void
    {
        $query->whereNull('verified_at')->where('expires_at', '>', CarbonImmutable::now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_attempts' => 'integer',
            'verification_code_sent_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
