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
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;

/**
 * One of a store's own delivery drivers, who signs in to the driver app with their phone number and a PIN.
 *
 * Signs in with the "driver" guard and only ever sees deliveries assigned to
 * them. Staff set and reset the PIN; there is no email or self-service reset.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $phone In E.164 format, for example +2348012345678.
 * @property string $pin Hashed like a password.
 * @property bool $is_active
 * @property CarbonImmutable|null $last_signed_in_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
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
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
