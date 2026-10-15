<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a shopper means to buy, before checkout: a signed-in customer's (one each) or a guest's, never both.
 *
 * A guest's cart is found only by the hash of the token it was given once;
 * the token itself is never stored. A cart holds variants and quantities:
 * prices, tax and discounts are worked out each time it is read, and stock is
 * never held for it. Its version goes up with every change.
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $customer_id
 * @property string|null $token_hash SHA-256 of a guest's token, in hex.
 * @property string|null $currency The currency the shopper chose; null for the store's own.
 * @property string|null $destination_country_code Where the shopper said they are, for the tax estimate; null for the store's own country.
 * @property int $version
 * @property CarbonImmutable $last_activity_at When the shopper last changed it.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Customer|null $customer
 * @property-read Collection<int, CartLine> $lines
 */
final class Cart extends Model
{
    use HasPublicId;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /** A new cart starts at version 1, as the database does. */
    protected $attributes = [
        'version' => 1,
    ];

    protected $fillable = [
        'customer_id',
        'token_hash',
        'currency',
        'destination_country_code',
        'last_activity_at',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * Its lines, oldest first.
     *
     * @return HasMany<CartLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CartLine::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'last_activity_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
