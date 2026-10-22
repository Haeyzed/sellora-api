<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a guest gets back to their order (Step 16): a token given once when the order is placed. Only its hash is stored, so a leaked database opens no orders.
 *
 * It travels only in the X-Order-Token header, never a URL, and lets the
 * guest see the order, pay again, download its invoice and credit notes, and
 * cancel it while unpaid, until it expires. Erasure removes it.
 *
 * @property int $id
 * @property int $order_id
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property-read Order $order
 */
final class OrderAccessToken extends Model
{
    /** The header the token travels in. */
    public const string HEADER = 'X-Order-Token';

    public const null UPDATED_AT = null;

    protected $fillable = [];

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
     * Whether a string could be a token at all, before looking it up.
     */
    public static function isWellFormed(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
