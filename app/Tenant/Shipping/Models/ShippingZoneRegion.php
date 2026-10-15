<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A country, or one of its states, that a zone covers. Not audited on its own: its zone records the whole list when it changes.
 *
 * @property int $id
 * @property int $shipping_zone_id
 * @property string $country_code ISO 3166-1 alpha-2.
 * @property string|null $state_code The state's code within the country (Geography); null for the whole country.
 * @property-read ShippingZone $zone
 */
final class ShippingZoneRegion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'country_code',
        'state_code',
    ];

    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_zone_id' => 'integer',
        ];
    }
}
