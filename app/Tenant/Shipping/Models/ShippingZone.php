<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Shipping\Data\ShippingRegion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Where the store ships to with one set of methods, such as "Lagos" or "West Africa": countries, or states of them.
 *
 * A region belongs to at most one zone, and a state's zone beats its
 * country's. One zone may instead cover the rest of the world: every country
 * no zone lists. Audited, with changes to its regions as one change.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name For staff, such as "West Africa".
 * @property bool $is_active An inactive zone's regions get no shipping; they don't fall through to another zone.
 * @property bool $covers_rest_of_world Covers every country no zone lists; such a zone has no regions.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, ShippingZoneRegion> $regions
 */
final class ShippingZone extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    /** The audit event recorded when a zone's regions are replaced, with the lists before and after. */
    public const string REGIONS_REPLACED = 'regions_replaced';

    protected $fillable = [
        'name',
        'is_active',
        'covers_rest_of_world',
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * @return HasMany<ShippingZoneRegion, $this>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(ShippingZoneRegion::class)->orderBy('country_code')->orderByRaw('state_code nulls first');
    }

    /**
     * @return HasMany<ShippingMethod, $this>
     */
    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    /**
     * Its regions as data, in a stable order.
     *
     * @return list<ShippingRegion>
     */
    public function regionList(): array
    {
        return array_values($this->regions->map(static fn (ShippingZoneRegion $region): ShippingRegion => new ShippingRegion($region->country_code, $region->state_code))->all());
    }

    /**
     * Audits a change to its regions as one change, with the lists before and after, such as ["NG"] → ["NG", "GH"].
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public function auditRegionsReplaced(array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }

        $this->auditEvent = self::REGIONS_REPLACED;
        $this->isCustomEvent = true;
        $this->auditCustomOld = ['regions' => $before];
        $this->auditCustomNew = ['regions' => $after];
        Event::dispatch(new AuditCustom($this));
        $this->auditCustomOld = $this->auditCustomNew = [];
        $this->isCustomEvent = false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'covers_rest_of_world' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
