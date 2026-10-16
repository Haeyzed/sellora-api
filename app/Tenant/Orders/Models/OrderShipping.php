<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Pricing\Enums\TaxOutcome;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use App\Tenant\Shipping\Models\ShippingMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How an order was to reach the buyer and what that cost, copied from the method they chose (Step 13). Never changed or deleted.
 *
 * @property int $id
 * @property int $order_id
 * @property int $shipping_method_id
 * @property string $method_name In the order's language.
 * @property ShippingFulfilment $fulfilment
 * @property string|null $carrier
 * @property string|null $carrier_service
 * @property string|null $pickup_country_code A pickup's address, as it was.
 * @property string|null $pickup_state_code
 * @property string|null $pickup_address_line1
 * @property string|null $pickup_city
 * @property string|null $pickup_postal_code
 * @property string $currency
 * @property Money $price Before free-shipping discounts.
 * @property Money $discount
 * @property Money $net After the discount, before tax.
 * @property Money $tax
 * @property Money $total What was charged for it, tax included.
 * @property TaxOutcome|null $tax_outcome Null when nothing was charged for it.
 * @property int|null $min_days The estimate it was sold with, in business days.
 * @property int|null $max_days
 * @property CarbonImmutable|null $earliest_on
 * @property CarbonImmutable|null $latest_on
 * @property CarbonImmutable $created_at
 * @property-read ShippingMethod $method
 */
final class OrderShipping extends Model
{
    public const ?string UPDATED_AT = null;

    protected $table = 'order_shipping';

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'shipping_method_id' => 'integer',
            'fulfilment' => ShippingFulfilment::class,
            'price' => MoneyCast::class.':currency',
            'discount' => MoneyCast::class.':currency',
            'net' => MoneyCast::class.':currency',
            'tax' => MoneyCast::class.':currency',
            'total' => MoneyCast::class.':currency',
            'tax_outcome' => TaxOutcome::class,
            'min_days' => 'integer',
            'max_days' => 'integer',
            'earliest_on' => 'immutable_date',
            'latest_on' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
