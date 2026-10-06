<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Shared\Auth\PasswordResetLinkNotification;
use App\Shared\Concerns\HasNormalisedEmail;
use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;

/**
 * A shopper with an account at one store. The same person at two stores has two separate accounts.
 *
 * Signs in on the store's storefront with the "customer" guard, and can only
 * ever see their own data.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property CarbonImmutable|null $last_signed_in_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class Customer extends User implements HasApiTokensContract
{
    use HasApiTokens;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use HasNormalisedEmail;
    use HasPublicId;
    use Notifiable;
    use SoftDeletes;

    public const string GUARD = 'customer';

    public const string PASSWORD_BROKER = 'customers';

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
     * Emails a link into the storefront to choose a new password.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(PasswordResetLinkNotification::forBroker(self::PASSWORD_BROKER, $token, $this->email));
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
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
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
