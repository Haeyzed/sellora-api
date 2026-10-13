<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Decimal;
use App\Shared\Money\DecimalCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * The tax rate of one tax class in one country, or in one of its states, such as "VAT 7.5%" for Standard in Nigeria.
 *
 * A state's rate replaces its country's rate; they never add up (section
 * 3.3). One rate per class and region, where an empty state is the
 * country-wide rate. Audited.
 *
 * @property int $id
 * @property string $public_id
 * @property int $tax_class_id
 * @property string $country_code ISO 3166-1 alpha-2.
 * @property string|null $state_code The state's code within the country; null for the country-wide rate.
 * @property string $name What the tax is called, such as "VAT" or "Sales tax", shown on receipts.
 * @property Decimal $rate A percentage with up to 4 decimal places, from 0 to 100: 7.5 means 7.5%.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TaxClass $taxClass
 */
final class TaxRate extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    protected $fillable = [
        'country_code',
        'state_code',
        'name',
        'rate',
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

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
            'tax_class_id' => 'integer',
            'rate' => DecimalCast::class.':4',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
