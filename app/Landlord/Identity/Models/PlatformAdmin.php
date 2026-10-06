<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Models;

use App\Shared\Auth\PasswordResetLinkNotification;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use App\Shared\Auth\TwoFactor\HasTwoFactorAuthentication;
use App\Shared\Concerns\HasNormalisedEmail;
use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\Landlord\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A member of Sellora's own team (support, finance, administrators), who manages the platform rather than a store.
 *
 * Signs in on the central domain with the "platform" guard. What they may do
 * is decided by platform roles and permissions. Two-factor authentication is
 * mandatory: until it is set up, the account can only set it up.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property CarbonImmutable|null $last_signed_in_at
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_used_timestep
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class PlatformAdmin extends User implements HasApiTokensContract, TwoFactorAuthenticatable
{
    use CentralConnection;
    use HasApiTokens;

    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory;

    use HasNormalisedEmail;
    use HasPublicId;
    use HasRoles;
    use HasTwoFactorAuthentication;
    use Notifiable;

    public const string GUARD = 'platform';

    public const string PASSWORD_BROKER = 'platform_admins';

    protected string $guard_name = self::GUARD;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Emails a link into the platform admin app to choose a new password.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(PasswordResetLinkNotification::forBroker(self::PASSWORD_BROKER, $token, $this->email));
    }

    protected static function newFactory(): PlatformAdminFactory
    {
        return PlatformAdminFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_signed_in_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
