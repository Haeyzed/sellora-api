<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Models;

use App\Tenant\Catalog\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * The tax class a variant was given. A variant without one is in the store's default class. Audited.
 *
 * Kept by Pricing beside the variant, like Inventory's stock settings, so
 * Catalog never depends on Pricing.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property int $tax_class_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductVariant $variant
 * @property-read TaxClass $taxClass
 */
final class VariantTaxClass extends Model implements AuditableContract
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id'];

    /**
     * The variant it describes, even when the variant is in the trash.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * @return BelongsTo<TaxClass, $this>
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'tax_class_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
