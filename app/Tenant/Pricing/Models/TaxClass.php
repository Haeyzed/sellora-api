<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Models;

use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A group of things taxed the same way, such as "Standard", "Reduced", "Zero-rated" or "Exempt", which each variant belongs to.
 *
 * Zero-rated and exempt differ: a zero-rated class is taxable at 0% (its
 * rates say so) and is still reported; an exempt class is outside tax and
 * never looks up a rate. Exactly one class is the store's default, for every
 * variant not given another. At most one class is used for shipping; none
 * means shipping isn't taxed. Audited.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name Unique in the store, ignoring case.
 * @property bool $is_exempt Outside tax: never taxed and no rate is looked up.
 * @property bool $is_default
 * @property bool $used_for_shipping
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class TaxClass extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    protected $fillable = [
        'name',
        'is_exempt',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_exempt' => false,
        'is_default' => false,
        'used_for_shipping' => false,
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDefault(Builder $query): void
    {
        $query->where('is_default', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_exempt' => 'boolean',
            'is_default' => 'boolean',
            'used_for_shipping' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
