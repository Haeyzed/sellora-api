<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Models;

use App\Shared\Auth\PasswordResetLinkNotification;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use App\Shared\Auth\TwoFactor\HasTwoFactorAuthentication;
use App\Shared\Concerns\HasNormalisedEmail;
use App\Shared\Concerns\HasPublicId;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Jobs\SyncStoreOwnerContact;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\StaffMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;

/**
 * Someone who works for a store, such as its owner, a manager or a cashier, and signs in to the store's dashboard.
 *
 * Lives in the store's own database and signs in with the "staff" guard.
 * What they may do is decided by the store's roles and permissions.
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
final class StaffMember extends User implements AuditableContract, HasApiTokensContract, TwoFactorAuthenticatable
{
    use Auditable;
    use HasApiTokens;

    /** @use HasFactory<StaffMemberFactory> */
    use HasFactory;

    use HasNormalisedEmail;
    use HasPublicId;
    use HasRoles;
    use HasTwoFactorAuthentication;
    use Notifiable;

    public const string GUARD = 'staff';

    public const string PASSWORD_BROKER = 'staff_members';

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
     * Only changes to their direct permissions are audited (through auditSync); profile and sign-in changes go in the activity log instead, so secrets never reach an audit record.
     *
     * @var list<string>
     */
    protected $auditEvents = [];

    /**
     * Emails a link into the store dashboard to choose a new password.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(PasswordResetLinkNotification::forBroker(self::PASSWORD_BROKER, $token, $this->email));
    }

    protected static function newFactory(): StaffMemberFactory
    {
        return StaffMemberFactory::new();
    }

    /**
     * The platform keeps the owner's name and email as the store's contact, so a change to them is sent on once it has committed (section 6).
     */
    protected static function booted(): void
    {
        self::updated(static function (self $staffMember): void {
            if ($staffMember->wasChanged(['name', 'email']) && $staffMember->hasRole(StaffRole::Owner->value)) {
                dispatch(new SyncStoreOwnerContact)->afterCommit();
            }
        });
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
