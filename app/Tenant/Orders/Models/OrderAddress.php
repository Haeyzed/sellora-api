<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Tenant\Orders\Enums\OrderAddressKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One version of an order's billing or shipping address: version 1 as placed, then each correction. A copy, never a link to a saved address.
 *
 * Rows are only ever added; erasure alone may blank them, keeping the
 * country and state.
 *
 * @property int $id
 * @property int $order_id
 * @property OrderAddressKind $kind
 * @property int $version
 * @property string|null $recipient_name Null once anonymised.
 * @property string|null $company
 * @property string|null $line1 Null once anonymised.
 * @property string|null $line2
 * @property string|null $city
 * @property string|null $state_code
 * @property string|null $postal_code
 * @property string $country_code
 * @property string|null $phone
 * @property string|null $created_by_type Who corrected it, by morph name; null as placed.
 * @property string|null $created_by_id
 * @property CarbonImmutable $created_at
 */
final class OrderAddress extends Model
{
    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return array{recipient_name: string|null, company: string|null, line1: string|null, line2: string|null, city: string|null, state_code: string|null, postal_code: string|null, country_code: string, phone: string|null}
     */
    public function details(): array
    {
        return [
            'recipient_name' => $this->recipient_name,
            'company' => $this->company,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state_code' => $this->state_code,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
            'phone' => $this->phone,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'kind' => OrderAddressKind::class,
            'version' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
