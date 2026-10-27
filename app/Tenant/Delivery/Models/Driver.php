<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Models;

use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\DriverFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;

/**
 * One of a store's own delivery drivers, who signs in to the driver app with their phone number and a PIN.
 *
 * Signs in with the "driver" guard and only ever sees deliveries assigned to
 * them. Staff never set or know the PIN: they issue a one-time setup code,
 * and the driver chooses their own PIN with it. An erased driver keeps the
 * row, with name, phone and PIN cleared.
 *
 * @property int $id
 * @property string $public_id
 * @property string|null $name Null once erased.
 * @property string|null $phone In E.164 format, for example +2348012345678. Null once erased.
 * @property string|null $pin Hashed like a password. Null until the driver chooses one.
 * @property bool $is_active
 * @property CarbonImmutable|null $last_signed_in_at
 * @property CarbonImmutable|null $erased_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DriverSetupCode> $setupCodes
 */
final class Driver extends Model implements AuthenticatableContract, HasApiTokensContract
{
    use Authenticatable;
    use HasApiTokens;

    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    use HasPublicId;

    public const string GUARD = 'driver';

    protected $fillable = [
        'name',
        'phone',
        'pin',
        'is_active',
    ];

    protected $hidden = [
        'pin',
    ];

    /**
     * Drivers sign in with a PIN, stored in the "pin" column, instead of a password.
     */
    public function getAuthPasswordName(): string
    {
        return 'pin';
    }

    public function isErased(): bool
    {
        return $this->erased_at !== null;
    }

    /**
     * @return HasMany<DriverSetupCode, $this>
     */
    public function setupCodes(): HasMany
    {
        return $this->hasMany(DriverSetupCode::class);
    }

    protected static function newFactory(): DriverFactory
    {
        return DriverFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pin' => 'hashed',
            'is_active' => 'boolean',
            'last_signed_in_at' => 'immutable_datetime',
            'erased_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
