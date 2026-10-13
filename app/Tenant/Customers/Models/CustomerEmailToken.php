<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending one-time link emailed to a customer, to verify their email or to move their account to a new one.
 *
 * Only the token's hash is stored, so a leaked database can't be used to
 * verify or take over accounts.
 *
 * @property int $id
 * @property int $customer_id
 * @property CustomerEmailTokenPurpose $purpose
 * @property string $email
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property-read Customer $customer
 */
final class CustomerEmailToken extends Model
{
    public const null UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'purpose',
        'email',
        'token_hash',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * The value stored for a token.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The link with this token, if it is for this purpose and hasn't expired.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUsable(Builder $query, string $token, CustomerEmailTokenPurpose $purpose): void
    {
        $query->where('token_hash', self::hash($token))
            ->where('purpose', $purpose->value)
            ->where('expires_at', '>', CarbonImmutable::now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => CustomerEmailTokenPurpose::class,
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
