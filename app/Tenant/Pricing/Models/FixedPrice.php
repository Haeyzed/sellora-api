<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Catalog\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A variant's fixed price in one of the store's other currencies, used as it is instead of converting the base price (section 3.3). Audited.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property string $currency_code ISO 4217; one of the store's currencies, never the base one.
 * @property Money $price
 * @property Money|null $compare_at_price Higher than the price when set.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductVariant $variant
 */
final class FixedPrice extends Model implements AuditableContract
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id'];

    /**
     * The variant it prices, even when the variant is in the trash.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'price' => MoneyCast::class.':currency_code',
            'compare_at_price' => MoneyCast::class.':currency_code',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
