<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code staff issued so a driver can choose their own PIN. Only a keyed hash is kept; the code was shown once.
 *
 * @property int $id
 * @property int $driver_id
 * @property string $code_hash
 * @property int $failed_attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $voided_at
 * @property string $issued_by_type
 * @property string $issued_by_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Driver $driver
 */
final class DriverSetupCode extends Model
{
    protected $hidden = [
        'code_hash',
    ];

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Codes not yet used or voided; one at most per driver. An expired one is still live until replaced, but no longer works.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeLive(Builder $query): void
    {
        $query->whereNull('used_at')->whereNull('voided_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
