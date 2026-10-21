<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Customers\Enums\SocialProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's sign-in with a provider (Step 10b), found only by the provider's own user ID, never by email; at most one per provider per customer.
 *
 * Provider tokens are never kept.
 *
 * @property int $id
 * @property string $public_id
 * @property int $customer_id
 * @property SocialProvider $provider
 * @property string $provider_user_id
 * @property string|null $email As the provider sent it, which may differ from the account's.
 * @property bool $email_verified Whether the provider said the email is verified.
 * @property bool $is_private_relay An Apple private relay address.
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable $created_at
 * @property-read Customer $customer
 */
final class CustomerIdentity extends Model
{
    use HasPublicId;

    public const null UPDATED_AT = null;

    protected $hidden = [
        'provider_user_id',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'provider' => SocialProvider::class,
            'email_verified' => 'boolean',
            'is_private_relay' => 'boolean',
            'last_used_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
