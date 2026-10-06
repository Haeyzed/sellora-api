<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Models;

use App\Shared\Auth\Models\Role;
use App\Shared\Concerns\HasNormalisedEmail;
use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\StaffInvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An invitation for someone to join a store's team, sent by email as a one-time link that expires.
 *
 * The staff member account is only created when the person accepts and
 * chooses their own password; until then the invitation holds the email and
 * the roles they will get. Only a hash of the link's token is stored.
 *
 * @property int $id
 * @property string $public_id
 * @property string $email
 * @property string|null $name
 * @property string $token_hash
 * @property int|null $invited_by_id
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Role> $roles
 * @property-read StaffMember|null $invitedBy
 */
final class StaffInvitation extends Model
{
    /** @use HasFactory<StaffInvitationFactory> */
    use HasFactory;

    use HasNormalisedEmail;
    use HasPublicId;

    protected $fillable = [
        'email',
        'name',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * Whether the link can still be used: not accepted, not cancelled and not expired.
     */
    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * Whether it can still be resent or cancelled: neither accepted nor cancelled yet (an expired one can be resent).
     */
    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null;
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'staff_invitation_roles');
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'invited_by_id');
    }

    /**
     * Invitations whose link still works.
     *
     * @param  Builder<self>  $query
     */
    protected function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', CarbonImmutable::now());
    }

    /**
     * Invitations that can still be resent or cancelled.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeOpen(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at');
    }

    protected static function newFactory(): StaffInvitationFactory
    {
        return StaffInvitationFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
