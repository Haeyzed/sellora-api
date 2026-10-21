<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Enums\SocialSignInTicketKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a finished social sign-in still needs from the customer (Step 10b): proof they own the existing account with the provider's email, or an email when the provider gave none.
 *
 * Only the ticket's hash is stored. It holds what the provider sent, so
 * nothing is asked of the provider again, and it expires.
 *
 * @property int $id
 * @property string $token_hash
 * @property SocialSignInTicketKind $kind
 * @property SocialProvider $provider
 * @property string $provider_user_id
 * @property string|null $email
 * @property bool $email_verified
 * @property bool $is_private_relay
 * @property string|null $name
 * @property int|null $customer_id The existing account to link to.
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 */
final class SocialSignInTicket extends Model
{
    public const null UPDATED_AT = null;

    protected $hidden = [
        'token_hash',
        'provider_user_id',
    ];

    /**
     * The value stored for a ticket.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SocialSignInTicketKind::class,
            'provider' => SocialProvider::class,
            'email_verified' => 'boolean',
            'is_private_relay' => 'boolean',
            'customer_id' => 'integer',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
